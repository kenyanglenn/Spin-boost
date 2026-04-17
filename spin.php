<?php
require_once 'db.php';
$pdo = getPDO();
$currentUser = getCurrentUser();

if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// Check if plan has changed since session started
if (!isset($_SESSION['plan_change_notified']) && isset($_SESSION['original_plan']) && $_SESSION['original_plan'] !== $currentUser['plan'] && $_SESSION['original_plan'] !== 'NONE') {
    // Plan has changed, show notification and logout
    $_SESSION['plan_change_notified'] = true; // Prevent showing again in same session
    echo '<!DOCTYPE html><html><head><title>Plan Changed</title><style>body{font-family:Arial;text-align:center;padding:50px;} .notification{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:20px;border-radius:8px;max-width:500px;margin:0 auto;}</style></head><body>';
    echo '<div class="notification">';
    echo '<h2>🎉 Plan Successfully Upgraded!</h2>';
    echo '<p>Your plan has been upgraded. Please logout and login again for the changes to take effect.</p>';
    echo '<a href="logout.php" style="background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;margin-top:15px;">Logout Now</a>';
    echo '</div>';
    echo '</body></html>';
    exit;
}

// Store original plan for this session (only if not already set)
if (!isset($_SESSION['original_plan'])) {
    $_SESSION['original_plan'] = $currentUser['plan'];
}

if ($currentUser['plan'] === 'NONE') {
    $pendingDeposit = getPendingDeposit($currentUser['id']);
    if ($pendingDeposit) {
        header('Location: waiting_for_approval.php');
    } else {
        header('Location: plan_selection.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_submit'])) {
    $phone = trim($_POST['topup_phone'] ?? '');
    $amount = filter_var($_POST['topup_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    if (!$phone || !$amount || $amount <= 0) {
        setFlashMessage('error', 'Enter a valid phone number and amount.');
    } else {
        $result = createPendingDeposit($currentUser['id'], $amount, null, $phone);
        setFlashMessage('success', 'Deposit request created. Please send the money to 0701144109 and wait for admin approval.');
        header('Location: waiting_for_approval.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_submit'])) {
    $amount = filter_var($_POST['withdraw_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $phone = trim($_POST['withdraw_phone'] ?? '');
    if ($amount <= 0 || empty($phone)) {
        setFlashMessage('error', 'Enter a valid withdrawal amount and phone number.');
    } else {
        $result = withdrawMoney($currentUser['id'], $amount, $phone);
        if ($result['success']) {
            setFlashMessage('success', $result['message']);
        } else {
            setFlashMessage('error', $result['message']);
        }
        header('Location: ' . str_replace('.php', '', $_SERVER['PHP_SELF']));
        exit;
    }
}

$currentUser = getCurrentUser();
$planLimits = getPlanLimits($currentUser['plan']);
$spinCount = getDailyUsage($pdo, $currentUser['id'], 'spin');
$puzzleCount = getDailyUsage($pdo, $currentUser['id'], 'puzzle');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['PHP_SELF']), '\/');
$referralLink = $scheme . '://' . $host . $basePath . '/?ref=' . urlencode($currentUser['referral_code']);
$referralData = getReferralEarnings($currentUser['id']);
$withdrawalHistory = getWithdrawalHistory($currentUser['id']);
$depositHistory = getDepositHistory($currentUser['id']);
$totalDeposited = getTotalDeposited($currentUser['id']);
$totalWinnings = getTotalWinnings($currentUser['id']);
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Spin Boost Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body data-wallet="<?php echo htmlspecialchars($currentUser['wallet']); ?>">
    <div class="app-shell">
        <header class="top-bar">
            <button class="hamburger" id="hamburger">☰</button>
            <div>
                <p class="eyebrow">Wallet Balance</p>
                <h1><?php echo number_format($currentUser['wallet'], 2); ?> KES</h1>
            </div>
            <div class="top-bar-right">
                <div class="plan-pill">Plan: <?php echo htmlspecialchars($currentUser['plan']); ?></div>
                <a href="logout.php" class="secondary-btn">Logout</a>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="toast <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
        <?php endif; ?>

        <section class="card intro-card">
            <div>
                <h2>Spin Boost</h2>
                <p>Choose your stake, spin the wheel, and collect rewards. House edge keeps the game realistic.</p>
            </div>
            <button class="primary-btn" id="openTopup">Top Up</button>
        </section>

        <section class="game-card">
            <div class="wheel-card">
                <div class="wheel-frame">
                    <div class="wheel" id="spinWheel">
                        <?php for ($i = 0; $i <= 9; $i++): ?>
                            <div class="segment segment-<?php echo $i; ?>">x<?php echo $i; ?></div>
                        <?php endfor; ?>
                    </div>
                    <div class="pointer"></div>
                </div>
                <div class="wheel-controls">
                    <label for="spinStake">Stake (KES)</label>
                    <input type="number" id="spinStake" placeholder="Enter stake" min="10" step="1" value="10">
                    <button class="secondary-btn" id="spinNow">Spin Now</button>
                    <p class="hint" id="spinLimitText">Remaining spins today: <?php echo $planLimits['spins'] - $spinCount; ?> / <?php echo $planLimits['spins']; ?></p>
                </div>
            </div>
            <div class="result-panel" id="spinResultPanel">
                <h3>Last spin</h3>
                <p id="spinResultText">Place your stake and hit spin to see the outcome.</p>
            </div>
        </section>

        <?php include 'puzzle.php'; ?>

        <section class="card referral-card">
            <h2>Referral</h2>
            <p>Share your custom link and earn rewards when users join with your referral.</p>
            <div class="referral-box">
                <input type="text" readonly value="<?php echo htmlspecialchars($referralLink); ?>">
                <button class="secondary-btn" id="copyReferral">Copy</button>
            </div>
            <p class="small">Referral rewards: Regular 10KES, Premium 25KES, Premium+ 50KES.</p>
        </section>
    </div>

    <?php include 'topup_modal.php'; ?>

    <div class="mobile-menu" id="mobileMenu">
        <div class="menu-header">
            <h2>Menu</h2>
            <button class="close-menu" id="closeMenu">×</button>
        </div>
        <nav class="menu-nav">
            <button class="menu-item" data-section="referrals">Referrals</button>
            <button class="menu-item" data-section="financials">Financials</button>
            <button class="menu-item" data-section="withdraw">Withdraw</button>
            <button class="menu-item" data-section="plan-change">Change Plan</button>
            <button class="menu-item" data-section="help">Help</button>
            <a href="analytics.php" class="menu-item" style="text-decoration: none; color: inherit;">📊 Game Mechanics</a>
        </nav>
        <div class="menu-content">
            <div class="menu-section" id="referrals-section">
                <h3>Referrals</h3>
                <div class="referral-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total Earnings:</span>
                        <span class="summary-value"><?php echo number_format($referralData['total'], 2); ?> KES</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Active Referrals:</span>
                        <span class="summary-value">
                            <?php
                            $active = 0;
                            foreach ($referralData['details'] as $ref) {
                                if ($ref['plan'] !== 'NONE') $active++;
                            }
                            echo $active;
                            ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Inactive Referrals:</span>
                        <span class="summary-value">
                            <?php
                            $inactive = count($referralData['details']) - $active;
                            echo $inactive;
                            ?>
                        </span>
                    </div>
                </div>
                <table class="referral-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Plan</th>
                            <th>Earnings (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referralData['details'] as $ref): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ref['username']); ?></td>
                                <td><?php echo htmlspecialchars($ref['plan']); ?></td>
                                <td><?php echo number_format($ref['earnings'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="menu-section" id="financials-section">
                <h3>Financials</h3>
                <div class="referral-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total Deposited:</span>
                        <span class="summary-value"><?php echo number_format($totalDeposited, 2); ?> KES</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Won:</span>
                        <span class="summary-value"><?php echo number_format($totalWinnings, 2); ?> KES</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Current Balance:</span>
                        <span class="summary-value"><?php echo number_format($currentUser['wallet'], 2); ?> KES</span>
                    </div>
                </div>
                <h4>Deposit History</h4>
                <table class="referral-table">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($depositHistory)): ?>
                            <tr>
                                <td colspan="4">No deposit history yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($depositHistory as $deposit): ?>
                                <tr>
                                    <td><?php echo number_format($deposit['amount'], 2); ?></td>
                                    <td><?php echo $deposit['plan_id'] ? 'Plan: ' . htmlspecialchars($deposit['plan_id']) : 'Wallet deposit'; ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($deposit['status'])); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($deposit['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <h4>Withdrawal History</h4>
                <table class="referral-table">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($withdrawalHistory)): ?>
                            <tr>
                                <td colspan="4">No withdrawals yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($withdrawalHistory as $withdrawal): ?>
                                <tr>
                                    <td><?php echo number_format($withdrawal['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($withdrawal['phone'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($withdrawal['status'])); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($withdrawal['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="menu-section" id="withdraw-section">
                <h3>Withdraw</h3>
                <form method="post" class="topup-form">
                    <label for="withdraw_amount">Amount (Min 5 KES)</label>
                    <input type="number" id="withdraw_amount" name="withdraw_amount" min="5" step="1" required>
                    <label for="withdraw_phone">Phone Number</label>
                    <input type="tel" id="withdraw_phone" name="withdraw_phone" placeholder="07XXXXXXXX" required>
                    <button type="submit" name="withdraw_submit" class="primary-btn">Withdraw</button>
                </form>
                <h4>Withdrawal History</h4>
                <table class="referral-table">
                    <thead>
                        <tr>
                            <th>Amount (KES)</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($withdrawalHistory)): ?>
                            <tr>
                                <td colspan="3">No withdrawals yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($withdrawalHistory as $withdrawal): ?>
                                <tr>
                                    <td><?php echo number_format($withdrawal['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($withdrawal['status']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($withdrawal['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="menu-section" id="plan-change-section">
                <h3>Change Plan</h3>
                <p>Upgrade or change your plan. Select a new plan and enter your phone number. Send the payment to 0701144109 after submitting.</p>
                <form method="post" class="topup-form" action="plan_selection.php">
                    <label for="plan">Select Plan</label>
                    <select id="plan" name="plan" required>
                        <option value="">Choose a plan...</option>
                        <option value="REGULAR" <?php echo $currentUser['plan'] === 'REGULAR' ? 'disabled' : ''; ?>>Regular - 20 KES (5 spins/day, 3 puzzles/day)</option>
                        <option value="PREMIUM" <?php echo $currentUser['plan'] === 'PREMIUM' ? 'disabled' : ''; ?>>Premium - 50 KES (20 spins/day, 25 puzzles/day)</option>
                        <option value="PREMIUM+" <?php echo $currentUser['plan'] === 'PREMIUM+' ? 'disabled' : ''; ?>>Premium+ - 100 KES (Unlimited)</option>
                    </select>
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="07XXXXXXXX or 2547XXXXXXXX" value="<?php echo htmlspecialchars($currentUser['phone']); ?>" required>
                    <button type="submit" class="primary-btn">Request Plan Change</button>
                </form>
                <div class="info-box" style="margin-top: 15px; background: #f8f9fa; border-left: 4px solid #667eea; padding: 10px; border-radius: 4px; font-size: 14px;">
                    <strong>Note:</strong> After admin approval, you will be logged out and need to login again for the plan change to take effect. Your dashboard access remains available during the approval process.
                </div>
            </div>
            <div class="menu-section" id="help-section">
                <h3>Help</h3>
                <div class="faq">
                    <div class="faq-item">
                        <button class="faq-question">How do I spin the wheel?</button>
                        <div class="faq-answer">Enter your stake (minimum 10 KES) and click 'Spin Now'. The wheel will rotate and show your multiplier.</div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">What are the plan limits?</button>
                        <div class="faq-answer">Regular: 5 spins/day, 3 puzzles/day. Premium: 20 spins/day, 25 puzzles/day. Premium+: Unlimited.</div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">How do referrals work?</button>
                        <div class="faq-answer">Share your referral link. When someone registers and buys a plan, you earn rewards: Regular 10 KES, Premium 25 KES, Premium+ 50 KES.</div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">How to withdraw money?</button>
                        <div class="faq-answer">Minimum withdrawal is 5000 KES. Use the Withdraw section in the menu.</div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">How to change plan?</button>
                        <div class="faq-answer">Use the Plan Selection page. All plan purchases are reviewed manually by admin.</div>
                    </div>
                </div>
                <p>Still need help? <a href="https://wa.me/254701144109" target="_blank" class="whatsapp-link">Message Support on WhatsApp</a></p>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
