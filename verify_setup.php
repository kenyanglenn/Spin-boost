<?php
/**
 * Setup Verification Script
 * Checks if database and tables are ready for payment processing
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

header('Content-Type: application/json');

$status = [
    'database_connected' => false,
    'deposits_table_exists' => false,
    'deposits_table_created' => false,
    'error' => null
];

try {
    // Try to get database connection
    $pdo = getPDO();
    $status['database_connected'] = true;

    // Check if deposits table exists
    $result = $pdo->query("SHOW TABLES LIKE 'deposits'");
    if ($result->rowCount() > 0) {
        $status['deposits_table_exists'] = true;
        echo json_encode($status);
        exit;
    }

    // Create deposits table if it doesn't exist
    $sql = <<<SQL
    CREATE TABLE deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        provider VARCHAR(50) NOT NULL COMMENT 'flutterwave or intasend',
        provider_reference VARCHAR(255) COMMENT 'Payment provider transaction ID',
        your_reference VARCHAR(255) NOT NULL UNIQUE COMMENT 'Your unique transaction identifier',
        status ENUM('pending','completed','failed','expired') NOT NULL DEFAULT 'pending',
        verification_timestamp TIMESTAMP NULL COMMENT 'When payment was verified',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_your_reference (your_reference),
        INDEX idx_provider_reference (provider_reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL;

    $pdo->exec($sql);
    $status['deposits_table_created'] = true;
    $status['deposits_table_exists'] = true;

} catch (Exception $e) {
    $status['error'] = $e->getMessage();
}

echo json_encode($status);
?>
