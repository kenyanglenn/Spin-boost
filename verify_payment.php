<?php
/**
 * Payment Verification Handler
 * 
 * CRITICAL SECURITY: This verifies payment with provider API
 * NEVER trust redirect alone - always verify server-to-server
 */

session_start();
require_once 'db.php';
require_once 'payment_config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $transaction_ref = $_POST['reference'] ?? $_GET['reference'] ?? null;
    $provider_ref = $_POST['provider_reference'] ?? $_GET['provider_reference'] ?? null;

    if (!$transaction_ref && !$provider_ref) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Transaction reference required']);
        exit;
    }

    $pdo = getPDO();
    
    if ($transaction_ref) {
        $stmt = $pdo->prepare("SELECT * FROM deposits WHERE your_reference = ? LIMIT 1");
        $stmt->execute([$transaction_ref]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM deposits WHERE provider_reference = ? LIMIT 1");
        $stmt->execute([$provider_ref]);
    }

    $deposit = $stmt->fetch();

    if (!$deposit) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Deposit record not found']);
        exit;
    }

    if ($deposit['status'] === 'completed') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Payment already verified',
            'status' => 'completed',
            'already_processed' => true
        ]);
        exit;
    }

    if (PAYMENT_PROVIDER === 'flutterwave') {
        $verification = verifyFlutterwavePayment($deposit['provider_reference'] ?? $deposit['your_reference']);
    } else if (PAYMENT_PROVIDER === 'intasend') {
        $verification = verifyIntaSendPayment($deposit['provider_reference'] ?? $deposit['your_reference']);
    } else {
        throw new Exception("Unknown payment provider");
    }

    if ($verification === false) {
        updateDepositStatus($deposit['id'], 'failed');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment verification failed']);
        exit;
    }

    if (!verifyPaymentAmount($verification, $deposit['amount'])) {
        error_log("SECURITY: Amount mismatch for deposit {$deposit['id']}. Expected: {$deposit['amount']}, Got: {$verification['amount']}");
        updateDepositStatus($deposit['id'], 'failed');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Amount verification failed']);
        exit;
    }

    if (!verifyPaymentStatus($verification)) {
        updateDepositStatus($deposit['id'], 'failed');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment not completed']);
        exit;
    }

    if ($verification['provider_id']) {
        $stmt = $pdo->prepare("UPDATE deposits SET provider_reference = ? WHERE id = ?");
        $stmt->execute([$verification['provider_id'], $deposit['id']]);
    }

    try {
        $pdo->beginTransaction();

        $verified_at = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'completed', verification_timestamp = ? WHERE id = ?");
        $stmt->execute([$verified_at, $deposit['id']]);

        // Check if this is a plan purchase (reference starts with PLAN_)
        $is_plan_purchase = strpos($deposit['your_reference'], 'PLAN_') === 0;

        if ($is_plan_purchase) {
            // Activate the plan
            $plan = $_SESSION['pending_plan_purchase']['plan'] ?? null;
            if ($plan) {
                $stmt = $pdo->prepare("UPDATE users SET plan = ? WHERE id = ?");
                $stmt->execute([$plan, $deposit['user_id']]);

                // Handle referral rewards
                $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
                $stmt->execute([$deposit['user_id']]);
                $user_data = $stmt->fetch();

                if ($user_data && $user_data['referred_by']) {
                    $referrer_id = getReferrerId($user_data['referred_by']);
                    if ($referrer_id) {
                        addReferralReward($referrer_id, $plan);
                    }
                }

                // Clear pending plan purchase
                unset($_SESSION['pending_plan_purchase']);

                $pdo->commit();

                error_log("Plan purchase completed. Plan: $plan, User ID: {$deposit['user_id']}, Amount: {$deposit['amount']}");

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Plan purchase completed successfully',
                    'plan' => $plan,
                    'amount' => $deposit['amount'],
                    'status' => 'completed',
                    'type' => 'plan_purchase'
                ]);
                exit;
            }
        }

        // Regular wallet deposit
        $stmt = $pdo->prepare("UPDATE users SET wallet = wallet + ? WHERE id = ?");
        $stmt->execute([$deposit['amount'], $deposit['user_id']]);

        $pdo->commit();

        error_log("Payment verified and wallet credited. Deposit ID: {$deposit['id']}, User ID: {$deposit['user_id']}, Amount: {$deposit['amount']}");

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified and wallet credited',
            'deposit_id' => $deposit['id'],
            'amount' => $deposit['amount'],
            'status' => 'completed',
            'type' => 'wallet_deposit'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Transaction error: " . $e->getMessage());
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Payment verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Verification error',
        'debug' => PAYMENT_ENVIRONMENT === 'sandbox' ? $e->getMessage() : null
    ]);
}

function verifyFlutterwavePayment($tx_ref) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, FLW_BASE_URL . '/transactions/verify_by_reference?tx_ref=' . urlencode($tx_ref));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . FLW_SECRET_KEY
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log('Flutterwave verification error: ' . $response);
        return false;
    }

    $data = json_decode($response, true);

    if ($data['status'] !== 'success') {
        return false;
    }

    $transaction = $data['data'];

    return [
        'provider_id' => $transaction['id'] ?? null,
        'amount' => floatval($transaction['amount']),
        'status' => $transaction['status'],
        'currency' => $transaction['currency'],
        'phone' => $transaction['customer']['phone_number'] ?? null
    ];
}

function verifyIntaSendPayment($reference) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, INTASEND_BASE_URL . '/payment-links/' . urlencode($reference) . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . INTASEND_SECRET_KEY
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log('IntaSend verification error: ' . $response);
        return false;
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['status'])) {
        return false;
    }

    return [
        'provider_id' => $data['id'] ?? null,
        'amount' => floatval($data['amount']),
        'status' => $data['status'],
        'currency' => $data['currency'],
        'phone' => $data['phone'] ?? null
    ];
}

function verifyPaymentAmount($verification, $expected_amount) {
    return abs($verification['amount'] - $expected_amount) < 0.01;
}

function verifyPaymentStatus($verification) {
    $successful_statuses = ['completed', 'success', 'paid'];
    return in_array(strtolower($verification['status']), $successful_statuses);
}

function updateDepositStatus($deposit_id, $status) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("UPDATE deposits SET status = ? WHERE id = ?");
    $stmt->execute([$status, $deposit_id]);
}
?>
