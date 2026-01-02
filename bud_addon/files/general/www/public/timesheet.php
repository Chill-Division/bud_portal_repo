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

        <div class="grid">
            <div class="glass-panel">
                <h3>Sign In / Out</h3>
                <form method="POST">
                    <label>Your Name</label>
                    <input type="text" name="staff_name" placeholder="Enter name" required list="staff_list">
                    
                    <!-- We could auto-populate this datalist from distinct names in DB if we wanted -->
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
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="3">No activity recorded today.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
