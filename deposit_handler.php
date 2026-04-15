<?php
/**
 * Deposit Handler - Initiates Payment
 * 
 * Flow:
 * 1. Validate user and amount
 * 2. Create pending deposit record
 * 3. Get payment link from provider
 * 4. Redirect to payment page
 */

session_start();
require_once 'db.php';
require_once 'payment_config.php';

header('Content-Type: application/json');

try {
    // Check if user is authenticated
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    $amount = isset($input['amount']) ? floatval($input['amount']) : null;
    $phone = isset($input['phone']) ? trim($input['phone']) : null;

    // Validation
    if (!$amount || !$phone) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Amount and phone are required']);
        exit;
    }

    if ($amount < MIN_DEPOSIT || $amount > MAX_DEPOSIT) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Amount must be between KES " . MIN_DEPOSIT . " and KES " . MAX_DEPOSIT
        ]);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Get user from database
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, username, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Generate unique transaction reference
    $transaction_ref = generateTransactionReference($user_id);

    // Create pending deposit record
    $stmt = $pdo->prepare(
        "INSERT INTO deposits (user_id, amount, provider, your_reference, status) 
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$user_id, $amount, PAYMENT_PROVIDER, $transaction_ref]);
    $deposit_id = $pdo->lastInsertId();

    // Get payment link based on provider
    // TODO: Implement payment provider integration
    $payment_link = null; // Placeholder - payment integration to be implemented

    if (!$payment_link) {
        // Update deposit to failed
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
        $stmt->execute([$deposit_id]);

        throw new Exception("Payment integration not yet implemented");
    }

    // Return success with payment link
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Redirecting to payment page...',
        'payment_link' => $payment_link,
        'transaction_ref' => $transaction_ref
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Deposit handler error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => PAYMENT_ENVIRONMENT === 'sandbox' ? $e->getTraceAsString() : 'Check server logs'
    ]);
}


?>
