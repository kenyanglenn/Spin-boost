<?php
/**
 * Plan Payment Handler
 * Integrates plan purchases with the new payment system
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
    $plan = isset($input['plan']) ? strtoupper(trim($input['plan'])) : null;
    $phone = isset($input['phone']) ? trim($input['phone']) : null;

    // Validate plan
    $valid_plans = ['REGULAR', 'PREMIUM', 'PREMIUM+'];
    if (!$plan || !in_array($plan, $valid_plans)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid plan selected']);
        exit;
    }

    // Get plan costs
    $plan_costs = [
        'REGULAR' => 20.00,
        'PREMIUM' => 50.00,
        'PREMIUM+' => 100.00
    ];
    $amount = $plan_costs[$plan];

    // Validate phone
    if (!$phone || !preg_match('/^254[0-9]{9}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid phone number required (254XXXXXXXXX)']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Get user from database
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, username, plan FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Check if user already has a plan
    if ($user['plan'] !== 'NONE') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You already have an active plan']);
        exit;
    }

    // Generate unique transaction reference for plan purchase
    $transaction_ref = 'PLAN_' . generateTransactionReference($user_id);

    // Create pending plan purchase record (we'll use deposits table with special reference)
    $stmt = $pdo->prepare(
        "INSERT INTO deposits (user_id, amount, provider, your_reference, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$user_id, $amount, 'intasend', $transaction_ref]);

    // Store plan info in session for verification later
    $_SESSION['pending_plan_purchase'] = [
        'plan' => $plan,
        'amount' => $amount,
        'transaction_ref' => $transaction_ref
    ];

    // Get IntaSend payment link
    $payment_result = getIntaSendPaymentLink($user_id, $amount, $phone, $transaction_ref, $user['username'] . ' - ' . $plan . ' Plan');

    if (!$payment_result) {
        // Update deposit to failed
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE your_reference = ?");
        $stmt->execute([$transaction_ref]);

        throw new Exception("Failed to initiate STK Push");
    }

    // Return success - STK Push has been initiated
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'STK Push initiated. Please check your phone for the MPesa prompt.',
        'transaction_ref' => $transaction_ref,
        'plan' => $plan,
        'amount' => $amount,
        'stk_push_initiated' => true
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => PAYMENT_ENVIRONMENT === 'sandbox' ? $e->getTraceAsString() : null
    ]);
}



    // Check multiple possible response keys
    if (isset($data['url'])) {
        return $data['url'];
    } elseif (isset($data['checkout_url'])) {
        return $data['checkout_url'];
    } elseif (isset($data['link'])) {
        return $data['link'];
    } else {
        error_log('IntaSend Response has no URL key: ' . json_encode($data));
        return null;
    }
}

/**
 * Generate IntaSend payment link for plan purchases
 */
function getIntaSendPaymentLink($user_id, $amount, $phone, $reference, $description) {
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

    error_log('IntaSend Plan STK Push Request - Code: ' . $http_code . ', Response: ' . $response);

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
    // Return success indicator
    if (isset($data['id']) || isset($data['reference']) || isset($data['checkout_url'])) {
        return 'stk_push_initiated'; // We'll handle this in the frontend
    } else {
        error_log('IntaSend STK Push Response: ' . json_encode($data));
        return null;
    }
}
?>