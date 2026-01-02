<?php
/**
 * Database Migration for Version 0.10
 * Run this once to add bundle tables
 */

require_once 'config.php';

echo "Starting database migration for v0.10...\n";

try {
    // Create product_bundles table
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
    echo "✓ Created product_bundles table\n";

    // Create bundle_items table
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
    echo "✓ Created bundle_items table\n";

    echo "\n✅ Migration completed successfully!\n";
    echo "You can now use the Bundles feature.\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
