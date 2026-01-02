<?php
require_once 'config.php';
require_once 'includes/audit.php';

$message = '';
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['staff_name'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($name && ($action === 'IN' || $action === 'OUT')) {
        try {
            $stmt = $pdo->prepare("INSERT INTO time_logs (staff_name, action) VALUES (?, ?)");
            $stmt->execute([$name, $action]);
            $message = "Recorded: $name Signed $action";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Get today's logs
$logs = $pdo->query("SELECT * FROM time_logs WHERE DATE(timestamp) = '$today' ORDER BY timestamp DESC")->fetchAll();

// Calculate Active Users
// We need the LATEST action for every user. 
// Subquery to get max ID per user, then join.
// Actually, simpler to just fetch all time_logs (or last few days) and process in PHP if dataset small, 
// OR use a proper window function / groupwise max.
// Let's use Groupwise Max.
$active_users_stmt = $pdo->query("
    SELECT t1.*
    FROM time_logs t1
    JOIN (
        SELECT staff_name, MAX(id) as max_id
        FROM time_logs
        GROUP BY staff_name
    ) t2 ON t1.id = t2.max_id
    WHERE t1.action = 'IN'
    ORDER BY t1.timestamp DESC
");
$active_users = $active_users_stmt->fetchAll();

// Duration Helper
function calculate_duration($logs, $current_log) {
    if ($current_log['action'] !== 'OUT') return '';
    
    // Find the immediate previous IN for this user
    foreach ($logs as $l) {
        // Since $logs is ordered DESC, we look for logs AFTER this one in the array (older in time)
        if ($l['id'] < $current_log['id'] 
            && $l['staff_name'] === $current_log['staff_name'] 
            && $l['action'] === 'IN') {
            
            $out_time = strtotime($current_log['timestamp']);
            $in_time = strtotime($l['timestamp']);
            $diff = $out_time - $in_time;
            
            // Format duration
            $h = floor($diff / 3600);
            $m = floor(($diff % 3600) / 60);
            return "{$h}h {$m}m";
        }
    }
    return '-';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Time Sheet</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Staff Time Sheet</h1>
        <?php if ($message): ?>
            <div class="glass-panel" style="margin-bottom: 1rem; border-color: var(--accent-color);">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <!-- Active Staff Panel -->
        <div class="glass-panel" style="margin-bottom: 2rem;">
            <h3>🟢 Currently Signed In</h3>
            <?php if (empty($active_users)): ?>
                <p style="opacity: 0.7;">No staff currently active.</p>
            <?php else: ?>
                <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                    <?php foreach ($active_users as $user): ?>
                        <div class="glass-panel" style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success-color, #10b981); padding: 1rem;">
                            <h4 style="margin: 0;"><?= h($user['staff_name']) ?></h4>
                            <p style="font-size: 0.8rem; margin: 0.5rem 0;">
                                Since: <?= date('H:i', strtotime($user['timestamp'])) ?>
                            </p>
                            <form method="POST" onsubmit="return confirm('Sign out <?= h($user['staff_name']) ?>?');" style="margin-top: 0.5rem;">
                                <input type="hidden" name="staff_name" value="<?= h($user['staff_name']) ?>">
                                <input type="hidden" name="action" value="OUT">
                                <button type="submit" class="btn" style="width: 100%; font-size: 0.8rem; padding: 0.25rem; background: #ef4444;">Sign Out</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid">
            <div class="glass-panel">
                <h3>Manual Entry</h3>
                <form method="POST">
                    <label>Your Name</label>
                    <input type="text" name="staff_name" placeholder="Enter name" required list="staff_list">
                    
                    <datalist id="staff_list">
                        <?php 
                        $distinct_names = $pdo->query("SELECT DISTINCT staff_name FROM time_logs LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
                        foreach($distinct_names as $dn) echo "<option value='".h($dn)."'>";
                        ?>
                    </datalist>

                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <button type="submit" name="action" value="IN" class="btn" style="flex: 1; background: var(--accent-color);">Sign IN</button>
                        <button type="submit" name="action" value="OUT" class="btn" style="flex: 1; background: #ef4444;">Sign OUT</button>
                    </div>
                </form>
            </div>

            <div class="glass-panel">
                <h3>Today's Activity (<?= $today ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Name</th>
                            <th>Action</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('H:i', strtotime($log['timestamp'])) ?></td>
                            <td><strong><?= h($log['staff_name']) ?></strong></td>
                            <td>
                                <span style="
                                    background: <?= $log['action'] === 'IN' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' ?>; 
                                    color: <?= $log['action'] === 'IN' ? '#047857' : '#b91c1c' ?>;
                                    padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.8rem; font-weight: bold;
                                ">
                                    <?= $log['action'] ?>
                                </span>
                            </td>
                            <td style="font-size: 0.9rem; opacity: 0.8;">
                                <?= calculate_duration($logs, $log) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4">No activity recorded today.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
