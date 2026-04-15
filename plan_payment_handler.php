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
    $stmt->execute([$user_id, $amount, PAYMENT_PROVIDER, $transaction_ref]);

    // Store plan info in session for verification later
    $_SESSION['pending_plan_purchase'] = [
        'plan' => $plan,
        'amount' => $amount,
        'transaction_ref' => $transaction_ref
    ];

    // Get payment link based on provider
    if (PAYMENT_PROVIDER === 'flutterwave') {
        $payment_link = getFlutterwavePaymentLink($user_id, $amount, $phone, $transaction_ref, $user['username'] . ' - ' . $plan . ' Plan');
    } elseif (PAYMENT_PROVIDER === 'paystack') {
        $payment_link = getPaystackPaymentLink($user_id, $amount, $phone, $transaction_ref, $user['username'] . ' - ' . $plan . ' Plan');
    } else if (PAYMENT_PROVIDER === 'intasend') {
        $payment_link = getIntaSendPaymentLink($user_id, $amount, $phone, $transaction_ref, $user['username'] . ' - ' . $plan . ' Plan');
    } else {
        throw new Exception("Unknown payment provider");
    }

    if (!$payment_link) {
        // Update deposit to failed
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE your_reference = ?");
        $stmt->execute([$transaction_ref]);

        throw new Exception("Failed to generate payment link");
    }

    // Return success with payment link
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Redirecting to payment page...',
        'payment_link' => $payment_link,
        'transaction_ref' => $transaction_ref,
        'plan' => $plan,
        'amount' => $amount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => PAYMENT_ENVIRONMENT === 'sandbox' ? $e->getTraceAsString() : null
    ]);
}

/**
 * Generate Flutterwave payment link for plan purchase
 */
function getFlutterwavePaymentLink($user_id, $amount, $phone, $reference, $description) {
    $payload = [
        'tx_ref' => $reference,
        'amount' => (string)$amount,
        'currency' => PAYMENT_CURRENCY,
        'payment_options' => 'card,mobilemoney,ussd',
        'redirect_url' => PAYMENT_RETURN_URL,
        'customer' => [
            'email' => 'user' . $user_id . '@spinboost.local',
            'phonenumber' => $phone,
            'name' => 'User ' . $user_id
        ],
        'customizations' => [
            'title' => 'SpinBoost - Plan Purchase',
            'description' => $description
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
        error_log('Flutterwave Plan Payment Error: ' . $response);
        return null;
    }

    $data = json_decode($response, true);

    if ($data['status'] !== 'success') {
        error_log('Flutterwave Plan Payment API Error: ' . json_encode($data));
        return null;
    }

    return $data['data']['link'] ?? null;
}

/**
 * Generate Paystack payment link for plan purchase
 */
function getPaystackPaymentLink($user_id, $amount, $phone, $reference, $description) {
    // Paystack initialize transaction API
    $payload = [
        'email' => 'user' . $user_id . '@spinboost.local',
        'amount' => $amount * 100, // Paystack expects amount in kobo (multiply by 100)
        'reference' => $reference,
        'callback_url' => PAYMENT_RETURN_URL,
        'metadata' => [
            'user_id' => $user_id,
            'phone' => $phone,
            'plan_description' => $description,
            'custom_fields' => [
                [
                    'display_name' => 'Plan Description',
                    'variable_name' => 'plan_description',
                    'value' => $description
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
        error_log('Paystack Plan Payment Error: ' . $response);
        return null;
    }

    $data = json_decode($response, true);

    if (!$data['status']) {
        error_log('Paystack Plan Payment API Error: ' . json_encode($data));
        return null;
    }

    return $data['data']['authorization_url'] ?? null;
}

/**
 * Generate IntaSend payment link for plan purchase
 */
function getIntaSendPaymentLink($user_id, $amount, $phone, $reference, $description) {
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