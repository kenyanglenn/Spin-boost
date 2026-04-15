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
    // TODO: Implement payment provider integration
    $payment_link = null; // Placeholder - payment integration to be implemented

    if (!$payment_link) {
        // Update deposit to failed
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE your_reference = ?");
        $stmt->execute([$transaction_ref]);

        throw new Exception("Payment integration not yet implemented");
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