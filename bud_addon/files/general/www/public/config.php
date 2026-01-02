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