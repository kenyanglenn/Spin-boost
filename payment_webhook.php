<?php
/**
 * Payment Webhook Receiver
 * 
 * This endpoint is called by payment provider when payment is completed
 * IMPORTANT: Must verify webhook signature to prevent spoofing
 */

require_once 'db.php';
require_once 'payment_config.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
error_log('Webhook received: ' . $input);

try {
    $data = json_decode($input, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    // Handle IntaSend webhook
    handleIntaSendWebhook($data);

} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleFlutterwaveWebhook($data) {
    // TODO: Implement Flutterwave webhook verification and processing
    http_response_code(501);
    echo json_encode(['success' => false, 'message' => 'Flutterwave webhook not implemented']);
}

function handleIntaSendWebhook($data) {
    $headers = getallheaders();
    $intasend_signature = $headers['X-IntaSend-Signature'] ?? null;

    if ($intasend_signature) {
        $computed_hash = hash_hmac('sha256',
            file_get_contents('php://input'),
            INTASEND_SECRET_KEY
        );

        if ($computed_hash !== $intasend_signature) {
            error_log("IntaSend: Signature mismatch");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Signature verification failed']);
            exit;
        }
    }

    $reference = $data['reference_id'] ?? null;
    $amount = floatval($data['amount'] ?? 0);
    $status = $data['status'] ?? null;
    $provider_id = $data['id'] ?? null;

    if (!$reference || !$amount) {
        throw new Exception("Missing required webhook fields");
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM deposits WHERE your_reference = ? LIMIT 1");
    $stmt->execute([$reference]);
    $deposit = $stmt->fetch();

    if (!$deposit) {
        error_log("IntaSend webhook: Deposit not found for reference: $reference");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Deposit not found']);
        exit;
    }

    if ($status !== 'paid') {
        updateDepositStatus($deposit['id'], 'failed');
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Payment failed']);
        exit;
    }

    if (abs($amount - $deposit['amount']) >= 0.01) {
        error_log("IntaSend webhook: Amount mismatch for deposit {$deposit['id']}");
        updateDepositStatus($deposit['id'], 'failed');
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Amount mismatch']);
        exit;
    }

    if ($deposit['status'] === 'completed') {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Already processed']);
        exit;
    }

    // Check if this is a plan purchase (reference starts with PLAN_)
    if (strpos($deposit['your_reference'], 'PLAN_') === 0) {
        processPlanPayment($deposit['id'], $deposit['user_id'], $deposit['amount'], $provider_id);
    } else {
        processPayment($deposit['id'], $deposit['user_id'], $deposit['amount'], $provider_id);
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
}

function processPlanPayment($deposit_id, $user_id, $amount, $provider_id = null) {
    $pdo = getPDO();

    try {
        $pdo->beginTransaction();

        $verified_at = date('Y-m-d H:i:s');
        if ($provider_id) {
            $stmt = $pdo->prepare("UPDATE deposits SET status = 'completed', provider_reference = ?, verification_timestamp = ? WHERE id = ?");
            $stmt->execute([$provider_id, $verified_at, $deposit_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE deposits SET status = 'completed', verification_timestamp = ? WHERE id = ?");
            $stmt->execute([$verified_at, $deposit_id]);
        }

        // Determine plan based on amount
        $plan_costs = [
            20.00 => 'REGULAR',
            50.00 => 'PREMIUM',
            100.00 => 'PREMIUM+'
        ];

        $plan = $plan_costs[$amount] ?? 'NONE';

        if ($plan !== 'NONE') {
            $stmt = $pdo->prepare("UPDATE users SET plan = ? WHERE id = ?");
            $stmt->execute([$plan, $user_id]);

            // Check for referral rewards
            $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();

            if ($user_data && $user_data['referred_by']) {
                $referrer_id = getReferrerId($user_data['referred_by']);
                if ($referrer_id) {
                    addReferralReward($referrer_id, $plan);
                }
            }
        }

        $pdo->commit();
        error_log("Plan payment processed. ID: $deposit_id, User: $user_id, Plan: $plan, Amount: $amount");

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Plan payment error: " . $e->getMessage());
    }
}

function updateDepositStatus($deposit_id, $status) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("UPDATE deposits SET status = ? WHERE id = ?");
    $stmt->execute([$status, $deposit_id]);
}
?>
