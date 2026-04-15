<?php
require_once 'db.php';
$pdo = getPDO();
$currentUser = getCurrentUser();

if (!$currentUser) {
    header('Location: login.php');
    exit;
}

if ($currentUser['plan'] === 'NONE') {
    header('Location: plan_selection.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_submit'])) {
    $phone = trim($_POST['topup_phone'] ?? '');
    $amount = filter_var($_POST['topup_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    if (!$phone || !$amount || $amount <= 0) {
        setFlashMessage('error', 'Enter a valid phone number and amount.');
    } else {
        addWallet($pdo, $currentUser['id'], $amount);
        setFlashMessage('success', 'Top-up successful. Wallet updated by ' . number_format($amount, 2) . ' KES.');
        header('Location: ' . str_replace('.php', '', $_SERVER['PHP_SELF']));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_plan'])) {
    $newPlan = $_POST['new_plan'];
    $phone = trim($_POST['change_phone'] ?? '');
    $costs = [
        'REGULAR' => 20,
        'PREMIUM' => 50,
        'PREMIUM+' => 100
    ];
    if (empty($phone) || !isset($costs[$newPlan])) {
        setFlashMessage('error', 'Invalid plan or phone number.');
    } else {
        $cost = $costs[$newPlan];
        $paymentResult = initiateMpesaPayment($phone, $cost, 'Spin Boost plan change to ' . $newPlan);
        if ($paymentResult['success']) {
            changeUserPlan($currentUser['id'], $newPlan);
            setFlashMessage('success', $paymentResult['message'] . ' Your plan has been changed.');
            header('Location: ' . str_replace('.php', '', $_SERVER['PHP_SELF']));
            exit;
        } else {
            setFlashMessage('error', 'Payment prompt failed.');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_submit'])) {
    $amount = filter_var($_POST['withdraw_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($amount <= 0) {
        setFlashMessage('error', 'Enter a valid withdrawal amount.');
    } else {
        $result = withdrawMoney($currentUser['id'], $amount);
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
            <button class="menu-item" data-section="change-plan">Change Plan</button>
            <button class="menu-item" data-section="withdraw">Withdraw</button>
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
            <div class="menu-section" id="change-plan-section">
                <h3>Change Plan</h3>
                <form method="post" class="topup-form">
                    <label for="new_plan">New Plan</label>
                    <select name="new_plan" id="new_plan" required>
                        <option value="REGULAR">Regular - 20 KES</option>
                        <option value="PREMIUM">Premium - 50 KES</option>
                        <option value="PREMIUM+">Premium+ - 100 KES</option>
                    </select>
                    <label for="change_phone">Phone Number</label>
                    <input type="tel" id="change_phone" name="change_phone" placeholder="07XXXXXXXX" required>
                    <button type="submit" name="change_plan" class="primary-btn">Change Plan</button>
                </form>
            </div>
            <div class="menu-section" id="withdraw-section">
                <h3>Withdraw</h3>
                <form method="post" class="topup-form">
                    <label for="withdraw_amount">Amount (Min 5 KES)</label>
                    <input type="number" id="withdraw_amount" name="withdraw_amount" min="5" step="1" required>
                    <button type="submit" name="withdraw_submit" class="primary-btn">Withdraw</button>
                </form>
                <h4>Withdrawal History</h4>
                <table class="withdrawal-table">
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
                        <div class="faq-answer">Use the Change Plan section in the menu. Payment via MPESA.</div>
                    </div>
                </div>
                <p>Still need help? <a href="https://wa.me/254701144109" target="_blank" class="whatsapp-link">Message Support on WhatsApp</a></p>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
