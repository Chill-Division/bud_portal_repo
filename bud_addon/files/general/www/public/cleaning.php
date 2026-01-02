<?php
require_once 'config.php';
require_once 'includes/audit.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_schedule') {
        $name = $_POST['name'];
        $frequency = $_POST['frequency'];
        $description = $_POST['description'];

        try {
            $stmt = $pdo->prepare("INSERT INTO cleaning_schedules (name, frequency, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $frequency, $description]);
            $id = $pdo->lastInsertId();
            Audit::log($pdo, 'cleaning_schedules', $id, 'INSERT', null, $_POST);
            $message = "Schedule created.";
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
    } elseif ($action === 'log_cleaning') {
        $sid = $_POST['schedule_id'];
        $staff = $_POST['staff_name'];
        $notes = $_POST['notes'];

        try {
            $stmt = $pdo->prepare("INSERT INTO cleaning_logs (schedule_id, staff_name, notes) VALUES (?, ?, ?)");
            $stmt->execute([$sid, $staff, $notes]);
            $message = "Cleaning logged successfully.";
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
    }
}

// Logic to determine what's due
$schedules = $pdo->query("SELECT * FROM cleaning_schedules WHERE is_active = 1")->fetchAll();
$due_items = [];
$history = [];

foreach ($schedules as $sch) {
    // Get last log
    $stmt = $pdo->prepare("SELECT completed_at, staff_name FROM cleaning_logs WHERE schedule_id = ? ORDER BY completed_at DESC LIMIT 1");
    $stmt->execute([$sch['id']]);
    $last = $stmt->fetch();

    $is_due = false;
    if (!$last) {
        $is_due = true;
    } else {
        $last_time = strtotime($last['completed_at']);
        $now = time();
        $diff_days = ($now - $last_time) / (60 * 60 * 24);

        switch ($sch['frequency']) {
            case 'Daily':
                if ($diff_days >= 1)
                    $is_due = true;
                break;
            case 'Weekly':
                if ($diff_days >= 7)
                    $is_due = true;
                break;
            case 'Fortnightly':
                if ($diff_days >= 14)
                    $is_due = true;
                break;
            case 'Monthly':
                if ($diff_days >= 28)
                    $is_due = true;
                break;
        }
    }

    $sch['last_completed'] = $last ? $last['completed_at'] : 'Never';
    $sch['last_staff'] = $last ? $last['staff_name'] : '-';

    if ($is_due) {
        $due_items[] = $sch;
    } else {
        $history[] = $sch;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= APP_NAME ?> - Cleaning
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Cleaning & Hygiene</h1>
        <?php if ($message): ?>
            <div class="glass-panel" style="margin-bottom: 1rem; border-color: var(--accent-color);">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Due Tasks -->
            <div style="grid-column: span 2;">
                <div class="glass-panel" style="border-left: 5px solid var(--accent-color);">
                    <h3>⚠️ Due Tasks</h3>
                    <?php if (empty($due_items)): ?>
                        <p>All clean! Nothing due right now.</p>
                    <?php else: ?>
                        <?php foreach ($due_items as $item): ?>
                            <div
                                style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--card-border);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>
                                            <?= h($item['name']) ?>
                                        </strong>
                                        <span style="font-size: 0.8em; opacity: 0.7;">(
                                            <?= $item['frequency'] ?>)
                                        </span>
                                        <p><small>
                                                <?= h($item['description']) ?>
                                            </small></p>
                                    </div>
                                    <button onclick='logClean(<?= $item["id"] ?>, "<?= h($item["name"]) ?>")' class="btn">Mark
                                        Done</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Create New Schedule -->
            <div>
                <div class="glass-panel">
                    <h3>Manage Schedules</h3>
                    <button onclick="document.getElementById('schedForm').style.display='block'" class="btn"
                        style="width: 100%;">+ Create Schedule</button>

                    <h4 style="margin-top: 1rem;">All Schedules</h4>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach (array_merge($due_items, $history) as $s): ?>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <?= h($s['name']) ?> <small>(
                                    <?= $s['frequency'] ?>)
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Schedule Form Modal -->
        <div id="schedForm" class="glass-panel"
            style="display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); width: 90%; max-width: 500px; z-index: 100; box-shadow: 0 0 50px rgba(0,0,0,0.5);">
            <h3>New Cleaning Schedule</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_schedule">
                <label>Task Name</label>
                <input type="text" name="name" required>

                <label>Frequency</label>
                <select name="frequency">
                    <option value="Daily">Daily</option>
                    <option value="Weekly">Weekly</option>
                    <option value="Fortnightly">Fortnightly</option>
                    <option value="Monthly">Monthly</option>
                </select>

                <label>Description</label>
                <textarea name="description" rows="2"></textarea>

                <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" onclick="document.getElementById('schedForm').style.display='none'"
                        style="background: transparent; border: 1px solid var(--text-color); color: var(--text-color);">Cancel</button>
                    <button type="submit" class="btn">Create</button>
                </div>
            </form>
        </div>

        <!-- Log Completion Modal -->
        <div id="logModal" class="glass-panel"
            style="display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); width: 90%; max-width: 500px; z-index: 100; box-shadow: 0 0 50px rgba(0,0,0,0.5);">
            <h3>Log Completion: <span id="log_task_name"></span></h3>
            <form method="POST">
                <input type="hidden" name="action" value="log_cleaning">
                <input type="hidden" name="schedule_id" id="log_schedule_id">

                <label>Completed By</label>
                <input type="text" name="staff_name" required>

                <label>Notes</label>
                <textarea name="notes" rows="2" placeholder="Any issues found?"></textarea>

                <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" onclick="document.getElementById('logModal').style.display='none'"
                        style="background: transparent; border: 1px solid var(--text-color); color: var(--text-color);">Cancel</button>
                    <button type="submit" class="btn">Submit Log</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function logClean(id, name) {
            document.getElementById('log_schedule_id').value = id;
            document.getElementById('log_task_name').innerText = name;
            document.getElementById('logModal').style.display = 'block';
        }
    </script>
</body>

</html>