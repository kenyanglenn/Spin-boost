<?php
require_once 'db.php';

$currentAdmin = getCurrentAdmin();
$existingAdmins = countAdmins();
$canRegister = ($currentAdmin && isAdminUser($currentAdmin['id'])) || $existingAdmins === 0;
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canRegister) {
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($phone) || empty($password)) {
        setFlashMessage('error', 'All fields are required.');
    } elseif ($password !== $confirmPassword) {
        setFlashMessage('error', 'Passwords do not match.');
    } elseif (strlen($password) < 6) {
        setFlashMessage('error', 'Password must be at least 6 characters.');
    } elseif (countAdmins() >= 5) {
        setFlashMessage('error', 'Maximum of 5 admins already registered.');
    } else {
        try {
            createAdminUser($username, $phone, $password);
            setFlashMessage('success', 'Admin account created successfully. Please log in.');
            header('Location: admin_login.php');
            exit;
        } catch (Exception $e) {
            setFlashMessage('error', $e->getMessage());
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Registration - Spin Boost</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Admin Registration</h2>

            <?php if ($flash): ?>
                <div class="toast <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
            <?php endif; ?>

            <?php if (!$canRegister): ?>
                <p>You must be logged in as an admin to register a new admin account.</p>
                <p class="auth-link"><a href="admin_login.php">Admin login</a></p>
                <p class="auth-link">Back to <a href="index.php">home</a></p>
            <?php else: ?>
                <p>Maximum admins allowed: 5. Currently registered: <?php echo $existingAdmins; ?>.</p>
                <form method="post" class="auth-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="primary-btn">Register Admin</button>
                </form>
                <p class="auth-link">Already have an admin account? <a href="admin_login.php">Login here</a></p>
                <p class="auth-link">Back to <a href="index.php">home</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
