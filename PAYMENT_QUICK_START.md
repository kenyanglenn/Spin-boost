# Quick Start: Testing Payment Integration

## Localhost Testing Checklist

### 1. Database Setup
```sql
-- Run this in phpMyAdmin or MySQL
CREATE TABLE deposits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  provider VARCHAR(50) NOT NULL,
  provider_reference VARCHAR(255),
  your_reference VARCHAR(255) NOT NULL UNIQUE,
  status ENUM('pending','completed','failed','expired') DEFAULT 'pending',
  verification_timestamp TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_your_reference (your_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. Create Provider Account

#### Choose FLUTTERWAVE:
```
1. Sign up: https://dashboard.flutterwave.com/signup
2. Settings → Developer
3. Copy TEST keys
4. Edit payment_config.php:
   define('PAYMENT_PROVIDER', 'flutterwave');
   define('PAYMENT_ENVIRONMENT', 'sandbox');
   define('FLW_PUBLIC_KEY', 'FLWPUBK_TEST-...');
   define('FLW_SECRET_KEY', 'FLWSECK_TEST-...');
```

#### OR IntaSend:
```
1. Sign up: https://dashboard.intasend.com
2. Settings → API Keys
3. Copy Sandbox keys
4. Edit payment_config.php:
   define('PAYMENT_PROVIDER', 'intasend');
   define('PAYMENT_ENVIRONMENT', 'sandbox');
   define('INTASEND_PUBLIC_KEY', 'pk_sandbox_...');
   define('INTASEND_SECRET_KEY', 'sk_sandbox_...');
```

### 3. Test Payment Flow

**Step 1:** Login to your site  
**Step 2:** Click "Top Up Wallet"  
**Step 3:** Enter:
- Phone: `254712345678`
- Amount: `100`

**Step 4:** Click "Proceed to Payment"  
**Step 5:** Use test credentials:
- **Flutterwave:** Card `4242 4242 4242 4242`, Exp `09/32`, CVV `999`, PIN `1234`
- **IntaSend:** Phone `254712345678`, any OTP

**Step 6:** Payment should succeed → Wallet credited

### 4. Verify in Database

```sql
-- Check deposit was created
SELECT * FROM deposits WHERE user_id = YOUR_USER_ID;

-- Should show status = 'completed'
-- Check user wallet updated:
SELECT id, wallet FROM users WHERE id = YOUR_USER_ID;
```

### 5. Common Test Scenarios

#### Successful Payment
- Amount: 100 KES
- Phone: 254712345678
- Status: ✅ Wallet credited

#### Failed Payment
- Cancel at provider page
- Status: ❌ Shows error, wallet unchanged

#### See Logs
- Open `c:\xampp\apache\logs\error.log`
- Or `c:\xampp\mysql\data\` for database logs

---

## Files Created/Modified

| File | Purpose |
|------|---------|
| `payment_config.php` | Configuration (edit this for keys) |
| `deposit_handler.php` | Initiates payment |
| `verify_payment.php` | Verifies with provider |
| `payment_webhook.php` | Webhook receiver |
| `payment_return.php` | Success/failure page |
| `topup_modal.php` | Updated form |
| `script.js` | Updated with payment handler |
| `spinboost_schema.sql` | Updated with deposits table |
| `PAYMENT_INTEGRATION_GUIDE.md` | Full deployment guide |

---

## Troubleshooting During Testing

| Problem | Fix |
|---------|-----|
| Payment link blank | Check API keys in payment_config.php |
| 401 error | Login first |
| Form won't submit | Check browser console for errors (F12) |
| Wallet not updated | Check database deposits table status |
| Webhook test fails | Localhost can't receive webhooks (use verification instead) |

---

## Going Live Later (Summary)

When you're ready to deploy:

1. Get LIVE API keys from provider
2. Change `payment_config.php`:
   - `'sandbox'` → `'live'`
   - Replace with live keys
   - Update SITE_URL to your domain
3. Set up webhook URL in provider dashboard
4. Run on HTTPS only
5. Test with small real payment first

That's it! Everything else works the same.

---

## Need Help?

- **Provider Issues:** Contact Flutterwave or IntaSend support
- **Code Issues:** Check error logs or console
- **Database Issues:** Check MySQL in phpMyAdmin
