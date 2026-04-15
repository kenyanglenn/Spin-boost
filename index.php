<?php
require_once 'db.php';

if (isset($_GET['ref'])) {
    $_SESSION['ref_code'] = trim($_GET['ref']);
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Spin Boost - Welcome</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="landing-container">
        <header class="landing-header">
            <h1>Spin Boost</h1>
            <p>Your ultimate gaming experience</p>
        </header>

        <?php if ($flash): ?>
            <div class="toast <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
        <?php endif; ?>

        <section class="hero-section">
            <h2>Join the Fun!</h2>
            <p>Spin wheels, solve puzzles, earn rewards, and refer friends for more bonuses.</p>
            <div class="cta-buttons">
                <a href="register.php" class="primary-btn">Register Now</a>
                <a href="login.php" class="secondary-btn">Login</a>
            </div>
        </section>

        <section class="features">
            <div class="feature-card">
                <h3>Spin Wheel</h3>
                <p>Place your stake and spin for multipliers up to x9!</p>
            </div>
            <div class="feature-card">
                <h3>Word Puzzles</h3>
                <p>Guess words and win big with our dynamic puzzle system.</p>
            </div>
            <div class="feature-card">
                <h3>Referral Rewards</h3>
                <p>Invite friends and earn KES for each successful referral.</p>
            </div>
        </section>

        <footer class="landing-footer">
            <p>&copy; 2026 Spin Boost. All rights reserved.</p>
        </footer>
    </div>

    <script src="script.js"></script>
</body>
</html>