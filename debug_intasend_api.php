<?php
/**
 * IntaSend API Debug Script
 * Test the API endpoint and see what's being returned
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'payment_config.php';

header('Content-Type: application/json');

// Test data - Try checkout endpoint instead
$test_payload = [
    'public_key' => INTASEND_PUBLIC_KEY,
    'amount' => 100,
    'currency' => PAYMENT_CURRENCY,
    'api_ref' => 'TEST_' . time() . '_' . rand(1000, 9999),
    'phone_number' => '254712345678',
    'first_name' => 'Test User',
    'email' => 'test@spinboost.local'
];

$debug = [
    'endpoint' => INTASEND_BASE_URL . '/checkout/',
    'method' => 'POST',
    'payload' => $test_payload,
    'headers_sent' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . substr(INTASEND_SECRET_KEY, 0, 10) . '...',
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
        'Authorization: Bearer ' . INTASEND_SECRET_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    // SSL verification bypass for sandbox/testing
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    
    curl_close($ch);

    $debug['http_code'] = $http_code;
    $debug['response'] = $response;

    if ($curl_error) {
        $debug['error'] = 'CURL Error (' . $curl_errno . '): ' . $curl_error;
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
?>
