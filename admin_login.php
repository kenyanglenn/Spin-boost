<?php
require_once 'db.php';

$currentAdmin = getCurrentAdmin();
if ($currentAdmin) {
    header('Location: admin_deposits.php');
    exit;
}

$flash = null;
$adminCount = countAdmins();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrPhone = trim($_POST['username_or_phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usernameOrPhone) || empty($password)) {
        setFlashMessage('error', 'Please enter username/phone and password.');
    } else {
        $loginResult = validateLogin($usernameOrPhone, $password);
        if ($loginResult['success']) {
            $user = $loginResult['user'];
            
            if (isAdminUser($user['id'])) {
                $_SESSION['admin_id'] = $user['id'];
                header('Location: admin_deposits.php');
                exit;
            } else {
                setFlashMessage('error', 'This account is not an admin account.');
            }
        } else {
            setFlashMessage('error', $loginResult['message']);
        }
    }
    $flash = getFlashMessage();
} else {
    $flash = getFlashMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Login - Spin Boost</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Admin Login</h2>

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

            <?php if ($adminCount === 0): ?>
                <p class="auth-link">No admin exists yet. <a href="admin_register.php">Create first admin</a></p>
            <?php else: ?>
                <p class="auth-link">Need an admin account? Contact an existing admin.</p>
            <?php endif; ?>

            <p class="auth-link">Back to <a href="index.php">home</a></p>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
