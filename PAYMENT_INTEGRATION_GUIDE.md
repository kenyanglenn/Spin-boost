# Payment Integration - Deployment Guide

## Summary

I've integrated payment deposits into your SpinBoost website using **Flutterwave** and **IntaSend** for mobile money payments (M-Pesa, Airtel Money, etc.).

### What's Been Done (Localhost)

✅ Database table for deposits created (`deposits` table)  
✅ Payment configuration file (`payment_config.php`)  
✅ Deposit initiation handler (`deposit_handler.php`)  
✅ Payment verification handler (`verify_payment.php`)  
✅ Webhook receiver (`payment_webhook.php`)  
✅ Payment return page with success/failure handling (`payment_return.php`)  
✅ Updated topup modal with payment form  
✅ Security checks: amount validation, duplicate prevention, atomic transactions  

---

## How It Works (Current System)

### Phase 1: User Initiates Deposit
1. User clicks "Top Up Wallet"
2. Enters phone number and amount
3. Form calls `deposit_handler.php`
4. System creates pending deposit record in database
5. Gets payment link from provider
6. User redirected to payment provider's hosted page

### Phase 2: Payment Processing
1. User completes payment with M-Pesa, Airtel Money, etc.
2. Payment provider processes transaction
3. Redirects user back to your site `payment_return.php`

### Phase 3: Verification & Wallet Credit (Security Critical)
1. `payment_return.php` calls `verify_payment.php`
2. `verify_payment.php` queries payment provider's API to verify payment
3. **NEVER trusts the redirect alone** - always verifies server-to-server
4. Checks:
   - Payment status is "completed"
   - Amount matches exactly
   - Transaction hasn't been processed before
5. If all checks pass: Updates deposit to "completed" and credits user wallet
6. Payment provider can also call your webhook (`payment_webhook.php`) as backup verification

---

## Setup on Localhost (For Testing)

### Step 1: Update Database
Run this SQL to create the deposits table:

```sql
CREATE TABLE deposits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  provider VARCHAR(50) NOT NULL,
  provider_reference VARCHAR(255) NOT NULL UNIQUE,
  your_reference VARCHAR(255) NOT NULL UNIQUE,
  status ENUM('pending','completed','failed','expired') NOT NULL DEFAULT 'pending',
  verification_timestamp TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_your_reference (your_reference),
  INDEX idx_provider_reference (provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Step 2: Create Payment Provider Account

**Choose ONE provider:**

#### Option A: Flutterwave (Supports Multiple Countries)
1. Go to https://dashboard.flutterwave.com/signup
2. Create account and verify email
3. Go to **Settings → Developer**
4. Copy TEST keys for sandbox testing
5. In `payment_config.php`:
   ```php
   define('PAYMENT_PROVIDER', 'flutterwave');
   define('PAYMENT_ENVIRONMENT', 'sandbox');
   ```

**Test Credentials (Sandbox):**
- Card: `4242 4242 4242 4242`
- Expiry: `09/32`
- CVV: `999`
- PIN: `1234`

#### Option B: IntaSend (Kenya-Optimized)
1. Go to https://dashboard.intasend.com/users/sign_up
2. Create account
3. Go to **Settings → API Keys**
4. Copy Sandbox keys
5. In `payment_config.php`:
   ```php
   define('PAYMENT_PROVIDER', 'intasend');
   define('PAYMENT_ENVIRONMENT', 'sandbox');
   ```

**Test Credentials (Sandbox):**
- Phone: `254712345678` (any format)
- Amount: Any amount KES 1 - 150,000
- Payment codes auto-send in test mode

### Step 3: Add API Keys to payment_config.php

Open `payment_config.php` and replace placeholder keys:

```php
// FLUTTERWAVE TEST KEYS
define('FLW_PUBLIC_KEY', 'FLWPUBK_TEST-XXXX'); // Replace with your test key
define('FLW_SECRET_KEY', 'FLWSECK_TEST-XXXX'); // Replace with your test key

// OR INTASEND TEST KEYS
define('INTASEND_PUBLIC_KEY', 'pk_sandbox_XXXX');
define('INTASEND_SECRET_KEY', 'sk_sandbox_XXXX');
```

### Step 4: Verify Files Exist

Make sure these files are in your `c:\xampp\htdocs\game\`:

- ✅ `payment_config.php`
- ✅ `deposit_handler.php`
- ✅ `verify_payment.php`
- ✅ `payment_webhook.php`
- ✅ `payment_return.php`
- ✅ `topup_modal.php` (updated)
- ✅ `script.js` (updated with payment form handler)

### Step 5: Test on Localhost

1. Make sure your user is logged in
2. Click "Top Up Wallet" button
3. Enter test phone: `254712345678`
4. Enter test amount: `100`
5. Click "Proceed to Payment"
6. You'll be redirected to provider's test page
7. Complete payment with test credentials
8. You'll be redirected back to `payment_return.php`
9. Wallet should be credited if payment succeeds

---

## Deployment Steps (When Going Live)

### ⚠️ CRITICAL: Before Going Live

#### 1. Get Live API Keys

**For Flutterwave:**
- Go to https://dashboard.flutterwave.com
- Go to **Settings → Developer**
- Switch from "TEST" to "LIVE"
- Copy LIVE keys (start with `FLWPUBK` not `FLWPUBK_TEST`)

**For IntaSend:**
- Go to https://dashboard.intasend.com
- Go to **Settings → API Keys**
- Copy production keys (start with `pk_live_` not `pk_sandbox_`)

#### 2. Update payment_config.php

Change these lines ONLY:

```php
// FROM:
define('PAYMENT_ENVIRONMENT', 'sandbox');
define('SITE_URL', 'http://localhost/game');

// TO:
define('PAYMENT_ENVIRONMENT', 'live');
define('SITE_URL', 'https://yourdomain.com'); // Your actual domain

// Update your actual API keys:
define('FLW_PUBLIC_KEY', 'FLWPUBK-YOUR_LIVE_KEY');
define('FLW_SECRET_KEY', 'FLWSECK-YOUR_LIVE_KEY');
// OR
define('INTASEND_PUBLIC_KEY', 'pk_live_YOUR_LIVE_KEY');
define('INTASEND_SECRET_KEY', 'sk_live_YOUR_LIVE_KEY');
```

#### 3. Configure Webhooks with Payment Provider

**For Flutterwave:**
1. Go to Dashboard → Settings → Webhooks
2. Set webhook URL to: `https://yourdomain.com/payment_webhook.php`
3. Choose events: Check "Charge" → "charge.completed"
4. Save

**For IntaSend:**
1. Go to Dashboard → Settings → Webhooks
2. Set webhook URL to: `https://yourdomain.com/payment_webhook.php`
3. Save

#### 4. Ensure HTTPS is Enabled

Payment providers require HTTPS (not HTTP). Your hosting should provide:
- SSL certificate (usually free with Let's Encrypt)
- HTTPS enabled on your domain

#### 5. Verify Database is Live

Make sure your live hosting has:
- MySQL database with `spinboost` database
- `deposits` table created (run the SQL above)

#### 6. Test with Small Amount

Before going fully live:
1. Make a small real payment (e.g., 1 KES)
2. Verify wallet gets credited
3. Check database for complete transaction record
4. Test with both M-Pesa and another method

---

## Security Checklist

- ✅ **Never trust redirect alone** - Always verify with API
- ✅ **Amount validation** - Check returned amount matches expected
- ✅ **Duplicate prevention** - Check status before crediting
- ✅ **Atomic transactions** - Wallet update and deposit status together
- ✅ **Webhook signatures** - Verify webhook is from provider
- ✅ **HTTPS only** - No HTTP in production
- ✅ **Secure keys** - Never commit API keys to version control
- ✅ **Logging** - All transactions logged for auditing
- ✅ **Error handling** - Failed payments don't credit wallet

---

## File Structure & Explanations

```
payment_config.php
├─ Centralized configuration
├─ Sandbox vs Live switching
├─ API credentials
└─ URLs and constants

deposit_handler.php
├─ Receives user deposit request
├─ Validates amount & phone
├─ Creates pending transaction
└─ Gets payment link from provider

verify_payment.php
├─ CRITICAL: Verifies with provider API
├─ Amount validation
├─ Status checking
├─ Atomic wallet credit
└─ Prevents duplicates

payment_webhook.php
├─ Webhook receiver (backup verification)
├─ Signature validation
├─ Processes verified payments
└─ Credits wallet if verified

payment_return.php
├─ User redirected here after payment
├─ Calls verify_payment.php
├─ Shows success/failure
└─ Auto-redirects or prompts retry
```

---

## Troubleshooting

### Payment Link Not Generated
- Check API keys in `payment_config.php`
- Check logs in `XAMPP/apache/logs/error.log`
- Verify you're on the correct environment (sandbox vs live)

### Payment Verified But Wallet Not Credited
- Check database for `deposits` record
- Check if status is "completed"
- Check user's wallet in `users` table
- Check error logs

### Webhook Not Called
- Verify webhook URL in provider dashboard is correct
- Check webhook logs in your error log
- Some providers delay webhooks - check verification endpoint manually

### Users Getting Timeout Errors
- Increase PHP timeout in `php.ini` to 60+ seconds
- Payment provider APIs can be slow
- Webhook handles delayed processing

---

## Key Functions

### In payment_config.php
```php
generateTransactionReference($user_id)  // Creates unique reference
```

### In deposit_handler.php
```php
getFlutterwavePaymentLink()  // Gets payment URL
getIntaSendPaymentLink()     // Gets payment URL
```

### In verify_payment.php
```php
verifyFlutterwavePayment()   // Verifies with provider
verifyIntaSendPayment()      // Verifies with provider
verifyPaymentAmount()        // Security check
verifyPaymentStatus()        // Check if payment succeeded
```

---

## Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| 401 Not authenticated | User not logged in | Login first |
| Cannot reach API | No internet/firewall | Check network |
| 403 Signature failed | Webhook signature wrong | Verify API keys match |
| Amount mismatch error | Decimal precision issue | System handles 0.01 tolerance |
| Duplicate transaction | Same reference processed twice | Check your_reference is unique |
| Wallet not credited | Verification failed | Check error logs |

---

## When to Use Webhooks vs Verification

### Use verify_payment.php When:
- User returns from payment page
- Admin manually checking status
- You want immediate confirmation

### Use payment_webhook.php When:
- Payment provider sends confirmation (automatic)
- User closed browser before returning
- Failed networks - still gets processed later

**Best Practice:** Use BOTH. Verification provides immediate feedback, webhooks ensure eventual processing.

---

## Support Contact Info

**Flutterwave Support:** support@flutterwave.co  
**IntaSend Support:** support@intasend.com

---

## Summary

You now have a production-ready payment system that:
✅ Redirects users to payment providers  
✅ Verifies transactions securely  
✅ Prevents duplicate/fake deposits  
✅ Credits user wallets atomically  
✅ Logs all transactions  
✅ Handles both sandbox and live environments  

**To go live:** Just update `payment_config.php` with live keys and domain. Everything else is ready!
