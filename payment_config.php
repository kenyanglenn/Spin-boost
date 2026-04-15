<?php
/**
 * Payment Gateway Configuration
 * Supports Flutterwave and IntaSend (Kenya)
 * 
 * IMPORTANT: When going live, switch ENVIRONMENT to 'live' and update API keys
 */

// ENVIRONMENT: Set to 'sandbox' for localhost testing, 'live' for production
define('PAYMENT_ENVIRONMENT', 'sandbox');

// PAYMENT PROVIDER: 'flutterwave', 'paystack', or 'intasend'
define('PAYMENT_PROVIDER', 'paystack');

/**
 * FLUTTERWAVE CONFIGURATION
 * Get keys from: https://dashboard.flutterwave.com/settings/developer
 */
if (PAYMENT_PROVIDER === 'flutterwave') {
    if (PAYMENT_ENVIRONMENT === 'sandbox') {
        define('FLW_PUBLIC_KEY', 'FLWPUBK_TEST-SANDBOXDEMOKEY-X');
        define('FLW_SECRET_KEY', 'FLWSECK_TEST-SANDBOXDEMOKEY-X');
        define('FLW_BASE_URL', 'https://api.flutterwave.com/v3');
    } else {
        // Live credentials
        define('FLW_PUBLIC_KEY', 'FLWPUBK-YOUR_LIVE_PUBLIC_KEY_HERE');
        define('FLW_SECRET_KEY', 'FLWSECK-YOUR_LIVE_SECRET_KEY_HERE');
        define('FLW_BASE_URL', 'https://api.flutterwave.com/v3');
    }
}

/**
 * PAYSTACK CONFIGURATION (Great for smaller businesses)
 * Get keys from: https://dashboard.paystack.com/settings/developer
 */
if (PAYMENT_PROVIDER === 'paystack') {
    if (PAYMENT_ENVIRONMENT === 'sandbox') {
        define('PAYSTACK_PUBLIC_KEY', 'pk_test_YOUR_PUBLIC_KEY_HERE');
        define('PAYSTACK_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY_HERE');
        define('PAYSTACK_BASE_URL', 'https://api.paystack.co');
    } else {
        // Live credentials
        define('PAYSTACK_PUBLIC_KEY', 'pk_live_YOUR_PUBLIC_KEY_HERE');
        define('PAYSTACK_SECRET_KEY', 'sk_live_YOUR_SECRET_KEY_HERE');
        define('PAYSTACK_BASE_URL', 'https://api.paystack.co');
    }
}

/**
 * GENERAL PAYMENT SETTINGS
 */
define('PAYMENT_CURRENCY', 'KES'); // Kenya Shillings - change if needed (USD, NGN, etc)
define('MIN_DEPOSIT', 50.00);
define('MAX_DEPOSIT', 100000.00);

// URLs for redirects (adjust domain when going live)
if (PAYMENT_ENVIRONMENT === 'sandbox') {
    define('SITE_URL', 'http://localhost/game');
} else {
    define('SITE_URL', 'https://yourdomain.com'); // UPDATE THIS when going live
}

define('PAYMENT_RETURN_URL', SITE_URL . '/payment_return.php');
define('PAYMENT_WEBHOOK_URL', SITE_URL . '/payment_webhook.php');

/**
 * Test accounts for sandbox testing:
 *
 * FLUTTERWAVE (All work in sandbox):
 * - Card: 4242 4242 4242 4242
 * - Expiry: 09/32
 * - CVV: 999
 * - PIN: 1234
 *
 * PAYSTACK (Nigerian cards work in sandbox):
 * - Card: 4084084084084081
 * - Expiry: 12/25
 * - CVV: 408
 *
 * INTASEND (M-Pesa Sandbox):
 * - Phone: 254712345678
 * - Amount: Any amount KES 1 - 150,000
 * - Confirmation code will be auto-sent in test mode
 * - Check console for payment link
 */

/**
 * Helper function to get appropriate API key
 */
function getPaymentApiKey($type = 'secret') {
    if (PAYMENT_PROVIDER === 'flutterwave') {
        return $type === 'secret' ? FLW_SECRET_KEY : FLW_PUBLIC_KEY;
    } elseif (PAYMENT_PROVIDER === 'paystack') {
        return $type === 'secret' ? PAYSTACK_SECRET_KEY : PAYSTACK_PUBLIC_KEY;
    } else {
        return $type === 'secret' ? INTASEND_SECRET_KEY : INTASEND_PUBLIC_KEY;
    }
}

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
