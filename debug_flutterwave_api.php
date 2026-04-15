<?php
/**
 * Flutterwave API Debug Script
 * Test the API endpoint and see what's being returned
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'payment_config.php';

header('Content-Type: application/json');

// Test data - Add public key for checkout
$test_payload = [
    'tx_ref' => 'TEST_' . time() . '_' . rand(1000, 9999),
    'amount' => '100',
    'currency' => 'KES',
    'payment_options' => 'card,mobilemoney,ussd',
    'redirect_url' => 'http://localhost/game/payment_return.php',
    'customer' => [
        'email' => 'test@spinboost.local',
        'phonenumber' => '254712345678',
        'name' => 'Test User'
    ],
    'customizations' => [
        'title' => 'SpinBoost - Test Payment',
        'description' => 'Testing payment integration',
        'logo' => ''
    ],
    'public_key' => FLW_PUBLIC_KEY  // Add public key for checkout
];

$debug = [
    'endpoint' => FLW_BASE_URL . '/payments',
    'method' => 'POST',
    'payload' => $test_payload,
    'headers_sent' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . substr(FLW_SECRET_KEY, 0, 10) . '...',
    ],
    'response' => null,
    'http_code' => null,
    'error' => null
];

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $debug['endpoint']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . FLW_SECRET_KEY
    ]);
    // SSL verification bypass for sandbox/testing
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    curl_close($ch);

    $debug['http_code'] = $http_code;
    $debug['response'] = $response;

    if ($curl_error) {
        $debug['error'] = 'CURL Error: ' . $curl_error;
    }

    // Try to parse as JSON
    if ($response) {
        $json_data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $debug['response_parsed'] = $json_data;
        } else {
            $debug['json_error'] = 'Failed to parse JSON: ' . json_last_error_msg();
        }
    }

    echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'Exception: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?></content>
<parameter name="filePath">c:\xampp\htdocs\game\debug_flutterwave_api.php