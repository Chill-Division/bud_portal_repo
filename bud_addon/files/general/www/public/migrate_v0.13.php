<?php
// migrate_v0.13.php
// Migration for Version 0.13.0
// Goals:
//   1. Add receiver_id column to chain_of_custody (if missing)
//   2. Add received_by column to chain_of_custody (if missing)
//   3. Create verified_receivers table (if missing)
//   4. Backfill received_by = 'Samantha' for existing rows with NULL received_by

try {
    $pdo->beginTransaction();

    // --- Check existing columns on chain_of_custody ---
    $cols = $pdo->query("PRAGMA table_info(chain_of_custody)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($cols, 'name');

    // 1. Add receiver_id if missing
    if (!in_array('receiver_id', $col_names)) {
        $pdo->exec("ALTER TABLE chain_of_custody ADD COLUMN receiver_id INTEGER");
    }

    // 2. Add received_by if missing
    if (!in_array('received_by', $col_names)) {
        $pdo->exec("ALTER TABLE chain_of_custody ADD COLUMN received_by TEXT");
    }

    // 3. Add completed_at if missing
    if (!in_array('completed_at', $col_names)) {
        $pdo->exec("ALTER TABLE chain_of_custody ADD COLUMN completed_at DATETIME");
    }

    // 4. Create verified_receivers table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS verified_receivers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact_person TEXT,
        address TEXT,
        phone TEXT,
        notes TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Backfill received_by = 'Samantha' for existing completed rows with no receiver name
    $pdo->exec("UPDATE chain_of_custody SET received_by = 'Samantha' WHERE received_by IS NULL AND status = 'Completed'");

    $pdo->commit();
    error_log("Migration v0.13 successful.");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Migration v0.13 failed: " . $e->getMessage());
    die("Migration v0.13 failed: " . $e->getMessage());
}
?>
