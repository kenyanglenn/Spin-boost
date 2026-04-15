<?php
/**
 * Test Paystack payment link generation directly
 */

require_once 'payment_config.php';

// Copy the Paystack function here to test it
function getPaystackPaymentLink($user_id, $amount, $phone, $reference, $username) {
    // Paystack initialize transaction API
    $payload = [
        'email' => 'user' . $user_id . '@spinboost.local',
        'amount' => $amount * 100, // Paystack expects amount in kobo (multiply by 100)
        'reference' => $reference,
        'callback_url' => 'http://localhost/game/payment_return.php',
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
// Test the function
$user_id = 1;
$amount = 100;
$phone = '254712345678';
$reference = 'TEST_' . time();
$username = 'Test User';

echo "Testing Paystack payment link generation...\n";
echo "Parameters: user_id=$user_id, amount=$amount, phone=$phone\n\n";

$result = getPaystackPaymentLink($user_id, $amount, $phone, $reference, $username);

if ($result) {
    echo "SUCCESS: Payment link generated!\n";
    echo "Link: $result\n";
} else {
    echo "FAILED: Could not generate payment link\n";
}
?></content>
<parameter name="filePath">c:\xampp\htdocs\game\test_deposit.php