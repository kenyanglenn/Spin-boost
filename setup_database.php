<?php
/**
 * Database setup script for Railway deployment
 * Run this once after deployment to set up the database tables
 */

require_once 'db.php';

try {
    $pdo = getPDO();

    // Read and execute the schema file
    $schema = file_get_contents('spinboost_schema.sql');

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $schema)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        }
    }

    echo "\n✅ Database setup completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>