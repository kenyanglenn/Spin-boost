<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $referred_by = $_SESSION['ref_code'] ?? null;

    if (empty($username) || empty($phone) || empty($password)) {
        setFlashMessage('error', 'All fields are required.');
    } elseif ($password !== $confirmPassword) {
        setFlashMessage('error', 'Passwords do not match.');
    } elseif (strlen($password) < 6) {
        setFlashMessage('error', 'Password must be at least 6 characters.');
    } else {
        try {
            $userId = registerUser($username, $phone, $password, $referred_by);
            $_SESSION['user_id'] = $userId;
            header('Location: plan_selection.php');
            exit;
        } catch (Exception $e) {
            setFlashMessage('error', 'Registration failed. Username or phone may already exist.');
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
    <title>Register - Spin Boost</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Register</h2>

            <?php if ($flash): ?>
                <div class="toast <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
            <?php endif; ?>

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
                <button type="submit" class="primary-btn">Register</button>
            </form>

            <p class="auth-link">Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>