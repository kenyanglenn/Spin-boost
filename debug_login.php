<?php
require 'db.php';

echo "<h2>Users in Database</h2>";
try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT id, username, phone, password FROM users');
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p style='color: red;'><strong>No users found in database!</strong></p>";
    } else {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Username</th><th>Phone</th><th>Password Hash</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['phone']) . "</td>";
            echo "<td>" . substr($user['password'], 0, 20) . "..." . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Test Login</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['test_username'] ?? '';
    $password = $_POST['test_password'] ?? '';
    
    $result = validateLogin($username, $password);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>

<form method="post">
    <input type="text" name="test_username" placeholder="Username or Phone" required>
    <input type="password" name="test_password" placeholder="Password" required>
    <button type="submit">Test Login</button>
</form>
