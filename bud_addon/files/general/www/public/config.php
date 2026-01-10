<?php
// config.php
// Database Configuration
date_default_timezone_set('Pacific/Auckland');

$db_file = '/data/bud.db';

try {
    // Check if the directory exists (for non-container testing compatibility)
    $db_dir = dirname($db_file);
    if (!is_dir($db_dir) && $db_dir !== '/data') {
        // Fallback for local testing if /data doesn't exist
        $db_file = __DIR__ . '/database/bud_inventory.db';
    }

    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Enable foreign keys for SQLite
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Auto-migration: Ensure database schema is up-to-date
    // Check if product_bundles table exists (v0.10)
    $tables_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='product_bundles'");
    if ($tables_check->rowCount() === 0) {
        // Run v0.10 migration
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_bundles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                sku TEXT,
                description TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bundle_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bundle_id INTEGER NOT NULL,
                stock_item_id INTEGER NOT NULL,
                quantity DECIMAL(10, 2) NOT NULL,
                FOREIGN KEY (bundle_id) REFERENCES product_bundles(id) ON DELETE CASCADE,
                FOREIGN KEY (stock_item_id) REFERENCES stock_items(id) ON DELETE CASCADE
            )
        ");
    }

    // Check for v0.12 schema (Once-off frequency support)
    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE name='cleaning_schedules'");
    $schema = $stmt->fetchColumn();
    // If table exists but schema doesn't have 'Once-off' in the Check constraint
    if ($schema && strpos($schema, 'Once-off') === false) {
        require_once 'migrate_v0.12.php';
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to sanitize output
function h($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// App Details
define('APP_NAME', 'BUD');
?>