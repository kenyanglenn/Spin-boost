# Payment Integration Fix - April 13, 2026

## Issue Found and Fixed

### Root Cause
The IntaSend API endpoint being called was incorrect:
- **Old Endpoint:** `/payment-links/` - Returns HTML form (causes 404 "Page not found" error)
- **Correct Endpoint:** `/send-money/initiate/` - Returns JSON with payment URL
- **Old Response:** HTML error page (causes JSON parsing error in frontend)

### Files Fixed
1. **deposit_handler.php** - Updated `getIntaSendPaymentLink()` function
2. **plan_payment_handler.php** - Updated `getIntaSendPaymentLink()` function

### Changes Made
- Changed API endpoint from `payment-links/` to `send-money/initiate/`
- Updated request payload to use correct IntaSend API parameters:
  - `wallet_id: 1` (default wallet)
  - `method: M-PESA` (mobile money method)
  - Removed invalid `public_key` field
  - Removed customer object (not needed for this endpoint)
- Updated response key from `link` to `url`
- Updated HTTP status codes accepted from `201` to `200 and 201`

## Setup Verification

### Step 1: Create/Verify Deposits Table
Visit: http://localhost/game/verify_setup.php

This will:
- Check if database is connected
- Check if deposits table exists
- Create the table if missing

### Step 2: Test Payment Links

Create a test account with:
- Username: testuser
- Phone: 254712345678 (test M-Pesa number)
- Password: testpass123

Then test the deposit flow:
1. Go to Spin Boost home → Wallet/Top Up
2. Enter amount: 100 (KES)
3. Enter phone: 254712345678 (must start with 254)
4. Click "Proceed to Payment"
5. Check browser console (F12) for response

### Step 3: Check Logs

Error logs: `C:\xampp\apache\logs\error.log`

Look for:
- "IntaSend Error: ..." entries (indicates API call failure)
- "IntaSend Invalid Response: ..." entries (indicates wrong response format)

## Testing Credentials

**IntaSend Sandbox:**
- Public Key: `ISPubKey_test_006cf6d6-145a-48cd-b67a-61cae81f5ad5`
- Secret Key: `ISSecretKey_test_5b1851fe-c66f-4ec8-ab57-39fe4914d853`
- Base URL: `https://sandbox.intasend.com/api/v1`

**Test M-Pesa Phone:** `254712345678`
**Test Amount Range:** KES 50 - 100,000

## Troubleshooting

### Error: "Payment link generation failed"
- Check error logs in Apache error.log
- Common issues:
  - IntaSend API keys invalid or expired
  - Network connectivity issue
  - M-Pesa method not available in test account

### Error: "Unexpected token '<'" in browser console
- This indicates PHP is returning HTML instead of JSON
- Check that deposits table exists
- Check database connection in db.php

### Error: "deposits table doesn't exist"
- Run verify_setup.php to automatically create it
- Or manually run spinboost_schema.sql in phpMyAdmin

## Next Steps After Testing

1. **Verify deposit creation** in database (should show status='pending')
2. **Check webhook handling** - Payment verification needs IntaSend webhook
3. **Test plan purchases** - Similar flow but different amounts
4. **Review security** - Verify payment amounts before crediting wallet

## Live Migration (When Ready)

Update `payment_config.php`:
```php
// Change to 'live'
define('PAYMENT_ENVIRONMENT', 'live');

// Update live credentials
define('INTASEND_PUBLIC_KEY', 'pk_live_YOUR_REAL_KEY');
define('INTASEND_SECRET_KEY', 'sk_live_YOUR_REAL_KEY');
define('SITE_URL', 'https://yourdomain.com');
```

Then test with real M-Pesa account before going public.
