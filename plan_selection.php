<?php
require_once 'db.php';

$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

if ($currentUser['plan'] !== 'NONE') {
    // This is a plan change request
    $isPlanChange = true;
} else {
    $isPlanChange = false;
}

$pendingDeposit = getPendingDeposit($currentUser['id']);
if ($pendingDeposit && $pendingDeposit['plan_id']) {
    header('Location: waiting_for_approval.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['selected_plan']) || isset($_POST['plan']))) {
    $plan = trim($_POST['selected_plan'] ?? $_POST['plan']);
    $paymentPhone = trim($_POST['payment_phone'] ?? $_POST['phone'] ?? '');
    $costs = [
        'REGULAR' => 20,
        'PREMIUM' => 50,
        'PREMIUM+' => 100
    ];

    if (!isset($costs[$plan]) || empty($paymentPhone)) {
        setFlashMessage('error', 'Select a plan and enter your phone number.');
    } elseif ($isPlanChange && $plan === $currentUser['plan']) {
        setFlashMessage('error', 'You cannot change to the same plan you already have.');
    } else {
        createPendingDeposit($currentUser['id'], $costs[$plan], $plan, $paymentPhone);
        if ($isPlanChange) {
            setFlashMessage('success', 'Plan change request created! Admin will review and approve your request shortly.');
        } else {
            setFlashMessage('success', 'Plan request created! Admin will review and approve your request shortly.');
        }
        header('Location: waiting_for_approval.php');
        exit;
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Choose Plan - Spin Boost</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="plan-container">
        <header class="plan-header">
            <h1>Choose Your Plan</h1>
            <p>Select a plan to unlock features and start playing!</p>
            <p>Your Wallet: <?php echo number_format($currentUser['wallet'], 2); ?> KES</p>
        </header>

        <?php if ($flash): ?>
            <div class="toast <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
        <?php endif; ?>

        <div class="plan-intro">
            <p>Choose a plan and enter the phone number you used for mobile money. After clicking "Deposit", send the payment to <strong>0701144109</strong>. Admin will verify and approve your plan.</p>
        </div>

        <form method="post" class="plan-form">
            <div class="form-group">
                <label for="payment_phone">Phone Number</label>
                <input type="tel" id="payment_phone" name="payment_phone" placeholder="07XXXXXXXX" required>
            </div>
            <input type="hidden" name="selected_plan" id="selected_plan" value="REGULAR">
            <section class="plans-grid">
                <div class="plan-card">
                    <h3>Regular</h3>
                    <p class="price">20 KES</p>
                    <ul>
                        <li>5 spins/day</li>
                        <li>3 word puzzles/day</li>
                    </ul>
                    <button type="button" class="primary-btn plan-select-btn" data-plan="REGULAR">Deposit for Regular</button>
                </div>

                <div class="plan-card premium">
                    <h3>Premium</h3>
                    <p class="price">50 KES</p>
                    <ul>
                        <li>20 spins/day</li>
                        <li>25 word puzzles/day</li>
                    </ul>
                    <button type="button" class="primary-btn plan-select-btn" data-plan="PREMIUM">Deposit for Premium</button>
                </div>

                <div class="plan-card premium-plus">
                    <h3>Premium+</h3>
                    <p class="price">100 KES</p>
                    <ul>
                        <li>Unlimited spins</li>
                        <li>Unlimited puzzles</li>
                    </ul>
                    <button type="button" class="primary-btn plan-select-btn" data-plan="PREMIUM+">Deposit for Premium+</button>
                </div>
            </section>
        </form>

        <footer class="plan-footer">
            <a href="login.php" class="secondary-btn">Back to Login</a>
        </footer>
    </div>

    <script>
        document.querySelectorAll('.plan-select-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.getElementById('selected_plan').value = button.dataset.plan;
                document.querySelector('form.plan-form').submit();
            });
        });
    </script>
</body>
</html>