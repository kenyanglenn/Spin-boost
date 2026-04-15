<?php
/**
 * Payment Return Page
 * 
 * User is redirected here after payment completion
 * This page verifies the payment and shows result
 */

session_start();
require_once 'payment_config.php';

$transaction_ref = $_GET['reference'] ?? $_GET['tx_ref'] ?? null;
$status = $_GET['status'] ?? 'unknown';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing - SpinBoost</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .payment-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .payment-container.processing {
            background: #f0f9ff;
        }
        .payment-container.success {
            background: #f0fdf4;
            border-left: 5px solid #22c55e;
        }
        .payment-container.failed {
            background: #fef2f2;
            border-left: 5px solid #ef4444;
        }
        .spinner {
            border: 4px solid #e5e7eb;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .status-icon {
            font-size: 50px;
            margin: 20px 0;
        }
        .success .status-icon { color: #22c55e; }
        .failed .status-icon { color: #ef4444; }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 30px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #2563eb;
        }
        .error-details {
            color: #dc2626;
            margin-top: 15px;
            font-size: 14px;
            max-height: 200px;
            overflow-y: auto;
            background: #fee2e2;
            padding: 10px;
            border-radius: 5px;
        }
        .success-details {
            color: #166534;
            margin-top: 15px;
            font-size: 14px;
            background: #dcfce7;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="payment-container processing" id="container">
        <h1>Processing Payment...</h1>
        <div class="spinner"></div>
        <p>Please wait while we verify your payment with the provider.</p>
        <p style="font-size: 12px; color: #666;">This should take a few seconds...</p>
    </div>

    <script>
        // Check if we have transaction reference
        const transactionRef = '<?php echo htmlspecialchars($transaction_ref ?? ''); ?>';
        
        if (!transactionRef) {
            document.getElementById('container').innerHTML = `
                <h1>❌ Payment Incomplete</h1>
                <p>No transaction reference found. Payment was cancelled or not started.</p>
                <a href="index.php" class="btn">Return to Home</a>
            `;
        } else {
            // Verify payment with our server
            verifyPayment(transactionRef);
        }

        async function verifyPayment(reference) {
            try {
                const response = await fetch('verify_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'reference=' + encodeURIComponent(reference)
                });

                const result = await response.json();

                const container = document.getElementById('container');

                if (result.success) {
                    // Payment verified!
                    container.className = 'payment-container success';
                    
                    // Check if this was a plan purchase
                    const isPlanPurchase = result.type === 'plan_purchase';
                    
                    container.innerHTML = `
                        <div class="status-icon">✅</div>
                        <h1>Payment Successful!</h1>
                        <div class="success-details">
                            <p><strong>Amount:</strong> KES ${parseFloat(result.amount).toFixed(2)}</p>
                            ${isPlanPurchase ? `<p><strong>Plan:</strong> ${result.plan}</p>` : ''}
                            <p><strong>Status:</strong> ${result.status}</p>
                            <p><strong>Transaction ID:</strong> ${result.deposit_id}</p>
                            <p style="margin-top: 10px; font-size: 12px;">
                                ${isPlanPurchase 
                                    ? 'Your plan has been activated! You can now access all features.' 
                                    : 'Your wallet has been credited with the deposit amount.'
                                }
                            </p>
                        </div>
                        <a href="index.php" class="btn">Return to Dashboard</a>
                    `;

                    // Auto-redirect after 3 seconds
                    setTimeout(() => window.location.href = 'index.php', 3000);
                } else {
                    // Payment failed
                    container.className = 'payment-container failed';
                    container.innerHTML = `
                        <div class="status-icon">❌</div>
                        <h1>Payment Failed</h1>
                        <p>${result.message || 'Payment verification failed'}</p>
                        <div class="error-details">
                            <strong>Reason:</strong> ${result.message || 'Unknown error'}
                            ${result.debug ? '<br><br><small>Debug: ' + result.debug + '</small>' : ''}
                        </div>
                        <p style="margin-top: 10px; color: #666;">Your wallet has NOT been charged.</p>
                        <a href="topup_modal.php" class="btn">Try Again</a>
                        <a href="index.php" class="btn" style="background: #6b7280; margin-left: 10px;">Return to Home</a>
                    `;
                }
            } catch (error) {
                const container = document.getElementById('container');
                container.className = 'payment-container failed';
                container.innerHTML = `
                    <div class="status-icon">⚠️</div>
                    <h1>Connection Error</h1>
                    <p>Could not reach payment verification service.</p>
                    <div class="error-details">
                        <strong>Error:</strong> ${error.message}
                        <p style="margin-top: 10px; font-size: 12px;">Please try again or contact support.</p>
                    </div>
                    <a href="topup_modal.php" class="btn">Try Again</a>
                    <a href="index.php" class="btn" style="background: #6b7280; margin-left: 10px;">Return to Home</a>
                `;
            }
        }
    </script>
</body>
</html>
