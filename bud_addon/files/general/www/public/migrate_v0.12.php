<?php
// migrate_v0.12.php
// Migration for Version 0.12.0
// Goal: Add 'Once-off' to cleaning_schedules frequency check constraint

try {
    $pdo->beginTransaction();

    // 1. Rename existing table
    $pdo->exec("ALTER TABLE cleaning_schedules RENAME TO cleaning_schedules_old");

    // 2. Create new table with updated CHECK constraint
    $pdo->exec("CREATE TABLE IF NOT EXISTS cleaning_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        frequency TEXT CHECK(frequency IN ('Daily', 'Weekly', 'Fortnightly', 'Monthly', 'Once-off')) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT 1
    )");

    // 3. Copy data
    $pdo->exec("INSERT INTO cleaning_schedules (id, name, description, frequency, created_at, is_active)
                SELECT id, name, description, frequency, created_at, is_active FROM cleaning_schedules_old");

    // 4. Drop old table
    $pdo->exec("DROP TABLE cleaning_schedules_old");

    $pdo->commit();
    error_log("Migration v0.12 successful: Added 'Once-off' frequency support.");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Migration v0.12 failed: " . $e->getMessage());
    die("Migration v0.12 failed: " . $e->getMessage());
}
?>