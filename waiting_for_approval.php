<?php
require_once 'db.php';

$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// Get approval status
$approvalStatus = getApprovalStatus($currentUser['id']);
$pendingDeposit = getPendingDeposit($currentUser['id']);

$canReturnToDashboard = false;
if ($approvalStatus['status'] === 'pending' && $pendingDeposit) {
    $canReturnToDashboard = ($currentUser['plan'] !== 'NONE' && empty($pendingDeposit['plan_id']));
}

// If user has approved deposit, redirect to appropriate page
if ($approvalStatus['status'] === 'approved') {
    if ($currentUser['plan'] === 'NONE') {
        header('Location: plan_selection.php');
    } else {
        header('Location: spin.php');
    }
    exit;
}

if ($approvalStatus['status'] === 'no_request') {
    if ($currentUser['plan'] === 'NONE') {
        header('Location: plan_selection.php');
    } else {
        header('Location: spin.php');
    }
    exit;
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Waiting for Approval - Spin Boost</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .approval-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .approval-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        
        .approval-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .approval-card h1 {
            font-size: 28px;
            margin: 20px 0;
            color: #333;
        }
        
        .approval-card p {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
            margin: 15px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            border-radius: 4px;
        }
        
        .info-box h3 {
            margin-top: 0;
            color: #333;
        }
        
        .info-box p {
            margin: 10px 0;
            font-size: 14px;
        }
        
        .payment-number-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            margin: 10px 0;
            font-size: 16px;
            font-weight: 600;
            color: #111;
        }
        
        .payment-number-box span {
            letter-spacing: 1px;
            color: #111;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 14px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .copy-btn:hover {
            background: #5568d3;
        }
        
        .btn-group {
            margin-top: 30px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .primary-btn, .secondary-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .primary-btn {
            background: #667eea;
            color: white;
        }
        
        .primary-btn:hover {
            background: #5568d3;
        }
        
        .secondary-btn {
            background: #e9ecef;
            color: #333;
        }
        
        .secondary-btn:hover {
            background: #dee2e6;
        }
        
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
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
        
        .rejection-notice {
            background: #ffebee;
            border: 1px solid #ef5350;
            color: #c62828;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="approval-container">
        <div class="approval-card">
            <?php if ($approvalStatus['status'] === 'pending'): ?>
                <div class="approval-icon">⏳</div>
                <h1>Waiting for Approval</h1>
                <p class="status-badge pending">PENDING REVIEW</p>

                <?php if ($pendingDeposit): ?>
                    <p>Your <?php echo $pendingDeposit['plan_id'] ? 'plan purchase' : 'wallet deposit'; ?> request is currently under manual review by our admin team.</p>
                    <div class="info-box">
                        <h3>Payment Instructions</h3>
                        <p><strong>Type:</strong> <?php echo $pendingDeposit['plan_id'] ? 'Plan purchase' : 'Wallet deposit'; ?></p>
                        <?php if ($pendingDeposit['plan_id']): ?>
                            <p><strong>Plan:</strong> <?php echo htmlspecialchars($pendingDeposit['plan_id']); ?></p>
                        <?php endif; ?>
                        <p><strong>Amount:</strong> <?php echo number_format($pendingDeposit['amount'], 2); ?> KES</p>
                        <p><strong>Your phone number:</strong> <?php echo htmlspecialchars($pendingDeposit['payment_phone'] ?? 'Not provided'); ?></p>
                        <p><strong>Send money to:</strong></p>
                        <div class="payment-number-box">
                            <span id="paymentNumber">0701144109</span>
                            <button type="button" class="copy-btn" onclick="copyPaymentNumber()">Copy</button>
                        </div>
                        <p class="deposit-info">After sending payment, the admin will verify it. Refresh this page to check status.</p>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <h3>What happens next?</h3>
                    <p>✓ Admin reviews the request</p>
                    <p>✓ Admin confirms the amount and payment details</p>
                    <p>✓ Approved deposits update your wallet or activate your plan</p>
                </div>

                <?php if ($canReturnToDashboard): ?>
                    <div class="info-box">
                        <h3>Dashboard Access</h3>
                        <p>Your dashboard remains available while this wallet deposit is pending. Your wallet will update only after approval.</p>
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        <h3>Dashboard Access</h3>
                        <p>Because this is a first-time plan approval request, dashboard access is blocked until approval. Please use the login page after the admin approves your request.</p>
                    </div>
                <?php endif; ?>

                <p style="color: #999; font-size: 13px;">Please refresh this page or login again to check for updates.</p>

                <div class="btn-group">
                    <button onclick="location.reload()" class="primary-btn">Refresh</button>
                    <?php if ($canReturnToDashboard): ?>
                        <a href="spin.php" class="secondary-btn">Back to Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="secondary-btn">Back to Login</a>
                    <?php endif; ?>
                </div>

            <?php elseif ($approvalStatus['status'] === 'rejected'): ?>
                <div class="approval-icon">❌</div>
                <h1>Request Rejected</h1>
                <p class="status-badge rejected">REJECTED</p>
                
                <div class="rejection-notice">
                    <strong>Reason:</strong><br>
                    <?php echo htmlspecialchars($approvalStatus['message']); ?>
                </div>
                
                <p>Please contact support or try submitting a new request.</p>
                
                <div class="btn-group">
                    <a href="plan_selection.php" class="primary-btn">Try Again</a>
                    <a href="logout.php" class="secondary-btn">Logout</a>
                </div>
                
            <?php else: ?>
                <div class="approval-icon">ℹ️</div>
                <h1>No Request Found</h1>
                <p>You don't have a pending deposit request.</p>
                
                <p>Please select a plan or make a deposit to get started.</p>
                
                <div class="btn-group">
                    <a href="plan_selection.php" class="primary-btn">Select Plan</a>
                    <a href="logout.php" class="secondary-btn">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function copyPaymentNumber() {
            const paymentNumber = document.getElementById('paymentNumber').textContent;
            navigator.clipboard.writeText(paymentNumber).then(() => {
                alert('Payment number copied: ' + paymentNumber);
            }).catch(() => {
                alert('Unable to copy automatically. Please copy the number manually.');
            });
        }

        // Auto-refresh every 30 seconds when status is pending
        const status = '<?php echo htmlspecialchars($approvalStatus['status']); ?>';
        if (status === 'pending') {
            setTimeout(() => {
                location.reload();
            }, 30000);
        }
    </script>
</body>
</html>
