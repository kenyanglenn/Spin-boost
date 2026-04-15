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

    if (PAYMENT_PROVIDER === 'flutterwave') {
        // TODO: Implement Flutterwave webhook handler
        handleFlutterwaveWebhook($data);
    } else if (PAYMENT_PROVIDER === 'intasend') {
        // TODO: Implement IntaSend webhook handler
        handleIntaSendWebhook($data);
    } else {
        throw new Exception("Unknown payment provider");
    }

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
    // TODO: Implement IntaSend webhook verification and processing
    http_response_code(501);
    echo json_encode(['success' => false, 'message' => 'IntaSend webhook not implemented']);
}

function processPayment($deposit_id, $user_id, $amount, $provider_id = null) {
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

        $stmt = $pdo->prepare("UPDATE users SET wallet = wallet + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);

        $pdo->commit();
        error_log("Deposit processed. ID: $deposit_id, User: $user_id, Amount: $amount");

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Transaction error: " . $e->getMessage());
    }
}

function updateDepositStatus($deposit_id, $status) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("UPDATE deposits SET status = ? WHERE id = ?");
    $stmt->execute([$status, $deposit_id]);
}
?>
