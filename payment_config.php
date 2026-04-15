<?php
define('PAYMENT_PUBLIC_KEY', getenv('PAYMENT_PUBLIC_KEY') ?: '');
define('PAYMENT_SECRET_KEY', getenv('PAYMENT_SECRET_KEY') ?: '');
define('PAYMENT_WEBHOOK_SECRET', getenv('PAYMENT_WEBHOOK_SECRET') ?: '');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8000');

/**
 * Helper function to get payment provider base URL
 */
function getPaymentBaseUrl() {
    if (PAYMENT_PROVIDER === 'flutterwave') {
        return FLW_BASE_URL;
    } elseif (PAYMENT_PROVIDER === 'paystack') {
        return PAYSTACK_BASE_URL;
    } else {
        return INTASEND_BASE_URL;
    }
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
