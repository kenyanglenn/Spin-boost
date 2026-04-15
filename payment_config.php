<?php
define('PAYMENT_PUBLIC_KEY', getenv('PAYMENT_PUBLIC_KEY') ?: '');
define('PAYMENT_SECRET_KEY', getenv('PAYMENT_SECRET_KEY') ?: '');
define('PAYMENT_WEBHOOK_SECRET', getenv('PAYMENT_WEBHOOK_SECRET') ?: '');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8000');

// IntaSend Configuration
define('INTASEND_PUBLIC_KEY', getenv('INTASEND_PUBLIC_KEY') ?: '');
define('INTASEND_SECRET_KEY', getenv('INTASEND_SECRET_KEY') ?: '');
define('INTASEND_BASE_URL', 'https://api.intasend.com/api/v1/');
define('PAYMENT_CURRENCY', 'KES');

// Payment limits
define('MIN_DEPOSIT', 50.00);
define('MAX_DEPOSIT', 100000.00);
define('MIN_WITHDRAWAL', 100.00);
define('MAX_WITHDRAWAL', 50000.00);

/**
 * Helper function to get payment provider base URL
 */
function getPaymentBaseUrl() {
    return INTASEND_BASE_URL;
}

/**
 * Generate unique transaction reference
 * Format: TXN_[timestamp]_[user_id]_[random]
 */
function generateTransactionReference($user_id) {
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    return 'TXN_' . $timestamp . '_' . $user_id . '_' . $random;
}
?>
