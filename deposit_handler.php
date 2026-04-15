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
    if (PAYMENT_PROVIDER === 'flutterwave') {
        $payment_link = getFlutterwavePaymentLink($user_id, $amount, $phone, $transaction_ref, $user['username']);
    } elseif (PAYMENT_PROVIDER === 'paystack') {
        $payment_link = getPaystackPaymentLink($user_id, $amount, $phone, $transaction_ref, $user['username']);
    } else if (PAYMENT_PROVIDER === 'intasend') {
        $payment_link = getIntaSendPaymentLink($user_id, $amount, $phone, $transaction_ref, $user['username']);
    } else {
        throw new Exception("Unknown payment provider");
    }

    if (!$payment_link) {
        // Update deposit to failed
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
        $stmt->execute([$deposit_id]);

        throw new Exception("Failed to generate payment link");
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

/**
 * Generate Flutterwave payment link
 */
function getFlutterwavePaymentLink($user_id, $amount, $phone, $reference, $username) {
    $payload = [
        'tx_ref' => $reference,
        'amount' => (string)$amount,
        'currency' => PAYMENT_CURRENCY,
        'payment_options' => 'card,mobilemoney,ussd',
        'redirect_url' => PAYMENT_RETURN_URL,
        'customer' => [
            'email' => 'user' . $user_id . '@spinboost.local',
            'phonenumber' => $phone,
            'name' => $username
        ],
        'customizations' => [
            'title' => 'SpinBoost - Wallet Top-up',
            'description' => 'Add funds to your wallet'
        ],
        'public_key' => FLW_PUBLIC_KEY
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, FLW_BASE_URL . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . FLW_SECRET_KEY
    ]);
    // SSL verification bypass for sandbox/testing (set to true in production)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log('Flutterwave Error: ' . $response);
        return null;
    }

    $data = json_decode($response, true);
    
    if ($data['status'] !== 'success') {
        error_log('Flutterwave API Error: ' . json_encode($data));
        return null;
    }

    return $data['data']['link'] ?? null;
}

/**
 * Generate Paystack payment link
 */
function getPaystackPaymentLink($user_id, $amount, $phone, $reference, $username) {
    // Paystack initialize transaction API
    $payload = [
        'email' => 'user' . $user_id . '@spinboost.local',
        'amount' => $amount * 100, // Paystack expects amount in kobo (multiply by 100)
        'reference' => $reference,
        'callback_url' => PAYMENT_RETURN_URL,
        'metadata' => [
            'user_id' => $user_id,
            'phone' => $phone,
            'custom_fields' => [
                [
                    'display_name' => 'Customer Name',
                    'variable_name' => 'customer_name',
                    'value' => $username
                ]
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYSTACK_BASE_URL . '/transaction/initialize');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
    ]);
    // SSL verification bypass for sandbox/testing
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log('Paystack Error: ' . $response);
        return null;
    }

    $data = json_decode($response, true);

    if (!$data['status']) {
        error_log('Paystack API Error: ' . json_encode($data));
        return null;
    }

    return $data['data']['authorization_url'] ?? null;
}

/**
 * Generate IntaSend payment link
 */
function getIntaSendPaymentLink($user_id, $amount, $phone, $reference, $username) {
    // IntaSend send-money API for receiving payments
    // Create a send-money session for M-Pesa payments

    $payload = [
        'transactions' => [
            [
                'phone_number' => $phone,
                'amount' => $amount,
                'reference' => $reference,
                'account' => '1'
            ]
        ],
        'currency' => PAYMENT_CURRENCY,
        'provider' => 'MPESA-B2C'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, INTASEND_BASE_URL . '/send-money/initiate/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . INTASEND_SECRET_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // SSL verification bypass for sandbox/testing (set to true in production)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Log the full response for debugging
    error_log('IntaSend Send-Money Request - Code: ' . $http_code . ', Response: ' . $response);

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
?>
