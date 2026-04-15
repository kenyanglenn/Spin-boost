<?php
/**
 * Paystack API Debug Script
 * Test the API endpoint and see what's being returned
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'payment_config.php';

header('Content-Type: application/json');

// Test data
$test_payload = [
    'email' => 'test@spinboost.local',
    'amount' => 100 * 100, // Paystack expects kobo
    'reference' => 'TEST_' . time() . '_' . rand(1000, 9999),
    'callback_url' => 'http://localhost/game/payment_return.php',
    'metadata' => [
        'custom_fields' => [
            [
                'display_name' => 'Test Payment',
                'variable_name' => 'test_payment',
                'value' => 'Debug test'
            ]
        ]
    ]
];

$debug = [
    'endpoint' => PAYSTACK_BASE_URL . '/transaction/initialize',
    'method' => 'POST',
    'payload' => $test_payload,
    'headers_sent' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . substr(PAYSTACK_SECRET_KEY, 0, 10) . '...',
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
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
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
<parameter name="filePath">c:\xampp\htdocs\game\debug_paystack_api.php