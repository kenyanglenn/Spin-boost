<?php
/**
 * Fix Deposits Table Schema
 * Makes provider_reference nullable since it's only set after payment verification
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

header('Content-Type: application/json');

try {
    $pdo = getPDO();

    // Drop existing deposits table
    $pdo->exec("DROP TABLE IF EXISTS deposits");

    // Recreate with correct schema (provider_reference nullable)
    $sql = <<<SQL
    CREATE TABLE deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        provider VARCHAR(50) NOT NULL COMMENT 'Payment provider (to be implemented)',
        provider_reference VARCHAR(255) UNIQUE COMMENT 'Payment provider transaction ID (set after verification)',
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

    echo json_encode([
        'success' => true,
        'message' => 'Deposits table recreated successfully with correct schema',
        'schema_fixed' => 'provider_reference is now nullable (will be set after payment verification)'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
