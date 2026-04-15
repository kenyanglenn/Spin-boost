<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrPhone = trim($_POST['username_or_phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = loginUser($usernameOrPhone, $password);
    if ($user) {
        if ($user['plan'] === 'NONE') {
            header('Location: plan_selection.php');
            exit;
        }
        header('Location: spin.php');
        exit;
    } else {
        setFlashMessage('error', 'Invalid credentials.');
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Login - Spin Boost</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Login</h2>

            <?php if ($flash): ?>
                <div class="toast <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="username_or_phone">Username or Phone</label>
                    <input type="text" id="username_or_phone" name="username_or_phone" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="primary-btn">Login</button>
            </form>

            <p class="auth-link">Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>