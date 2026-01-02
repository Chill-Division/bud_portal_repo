<?php
// includes/audit.php

class Audit
{
    public static function log($pdo, $table, $record_id, $action, $old_values = null, $new_values = null, $changed_by = 'SYSTEM')
    {
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_log (table_name, record_id, action, changed_by, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)");

            $old_json = $old_values ? json_encode($old_values) : null;
            $new_json = $new_values ? json_encode($new_values) : null;

            $stmt->execute([$table, $record_id, $action, $changed_by, $old_json, $new_json]);
        } catch (PDOException $e) {
            // Silently fail or log to file? For now, we want strict, so maybe throw?
            // But we don't want to break the app if audit fails?
            // User requirement: "All changes... atomic... replay everything". 
            // So we should probably die if audit fails to ensure integrity.
            die("Audit Log Failure: " . $e->getMessage());
        }
    }
}
?>