<?php
// Test script to check database and table
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once 'db.php';
    $pdo = getPDO();

    // Check if deposits table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'deposits'");
    $tableExists = $stmt->fetch();

    if ($tableExists) {
        echo "✅ Deposits table exists\n";

        // Try to insert a test record
        $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, provider, your_reference, status) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([1, 100.00, 'intasend', 'TEST_' . time(), 'pending']);

        if ($result) {
            echo "✅ Can insert into deposits table\n";
            // Clean up test record
            $pdo->query("DELETE FROM deposits WHERE your_reference LIKE 'TEST_%'");
        } else {
            echo "❌ Cannot insert into deposits table\n";
        }
    } else {
        echo "❌ Deposits table does not exist\n";
        echo "Please run this SQL in phpMyAdmin:\n\n";
        echo "CREATE TABLE deposits (\n";
        echo "  id INT AUTO_INCREMENT PRIMARY KEY,\n";
        echo "  user_id INT NOT NULL,\n";
        echo "  amount DECIMAL(10,2) NOT NULL,\n";
        echo "  provider VARCHAR(50) NOT NULL,\n";
        echo "  provider_reference VARCHAR(255),\n";
        echo "  your_reference VARCHAR(255) NOT NULL UNIQUE,\n";
        echo "  status ENUM('pending','completed','failed','expired') DEFAULT 'pending',\n";
        echo "  verification_timestamp TIMESTAMP NULL,\n";
        echo "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        echo "  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
        echo "  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,\n";
        echo "  INDEX idx_user_id (user_id),\n";
        echo "  INDEX idx_status (status)\n";
        echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
    }

} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "Check your database connection in db.php\n";
}
?>