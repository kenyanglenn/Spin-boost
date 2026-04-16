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
         VALUES (?, ?, 'intasend', ?, 'pending')"
    );
    $stmt->execute([$user_id, $amount, $transaction_ref]);
    $deposit_id = $pdo->lastInsertId();

    // Get IntaSend payment link
    $payment_result = getIntaSendPaymentLink($user_id, $amount, $phone, $transaction_ref, $user['username']);

    if (!$payment_result) {
        // Update deposit to failed
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
        $stmt->execute([$deposit_id]);

        throw new Exception("Failed to initiate STK Push");
    }

    // Return success - STK Push has been initiated
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'STK Push initiated. Please check your phone for the MPesa prompt.',
        'transaction_ref' => $transaction_ref,
        'stk_push_initiated' => true
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

/**
 * Generate IntaSend payment link for deposits
 */
function getIntaSendPaymentLink($user_id, $amount, $phone, $reference, $username) {
    // IntaSend STK Push API for receiving payments
    $payload = [
        'phone_number' => $phone,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
        'api_ref' => $reference
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, INTASEND_BASE_URL . 'payment/stk-push/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . INTASEND_SECRET_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    error_log('IntaSend STK Push Request - Code: ' . $http_code . ', Response: ' . $response);

    if ($curl_error) {
        error_log('IntaSend Curl Error: ' . $curl_error);
        return null;
    }

    if ($http_code !== 200 && $http_code !== 201) {
        error_log('IntaSend HTTP Error ' . $http_code . ': ' . $response);
        return null;
    }

    $data = json_decode($response, true);

    if (!$data) {
        error_log('IntaSend Invalid JSON Response: ' . $response);
        return null;
    }

    // STK Push doesn't return a URL, it initiates the push directly
    // Return a success indicator or checkout URL if available
    if (isset($data['checkout_url'])) {
        return $data['checkout_url'];
    } elseif (isset($data['id']) || isset($data['reference'])) {
        // STK Push initiated successfully, return a placeholder URL or success
        return 'stk_push_initiated'; // We'll handle this in the frontend
    } else {
        error_log('IntaSend STK Push Response: ' . json_encode($data));
        return null;
    }
}

?>
