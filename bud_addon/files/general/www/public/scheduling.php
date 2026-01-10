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
    } elseif ($action === 'edit_schedule') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $frequency = $_POST['frequency'];
        $description = $_POST['description'];

        try {
            $old_stmt = $pdo->prepare("SELECT * FROM cleaning_schedules WHERE id = ?");
            $old_stmt->execute([$id]);
            $old = $old_stmt->fetch();

            $stmt = $pdo->prepare("UPDATE cleaning_schedules SET name=?, frequency=?, description=? WHERE id=?");
            $stmt->execute([$name, $frequency, $description, $id]);
            Audit::log($pdo, 'cleaning_schedules', $id, 'UPDATE', $old, $_POST);
            $message = "Schedule updated.";
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
            $message = "Task logged successfully.";
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
    }
}

// Logic to determine what's due, upcoming, and completed
$schedules = $pdo->query("SELECT * FROM cleaning_schedules WHERE is_active = 1")->fetchAll();
$due_items = [];
$upcoming_items = [];
$history = [];

foreach ($schedules as $sch) {
    // Get last log
    $stmt = $pdo->prepare("SELECT completed_at, staff_name FROM cleaning_logs WHERE schedule_id = ? ORDER BY completed_at DESC LIMIT 1");
    $stmt->execute([$sch['id']]);
    $last = $stmt->fetch();

    $is_due = false;
    $is_upcoming = false;
    $threshold_days = 0;

    if (!$last) {
        $is_due = true;
    } else {
        $last_time = strtotime($last['completed_at']);
        $now = time();
        $diff_days = ($now - $last_time) / (60 * 60 * 24);

        switch ($sch['frequency']) {
            case 'Daily':
                $threshold_days = 1;
                break;
            case 'Weekly':
                $threshold_days = 7;
                break;
            case 'Fortnightly':
                $threshold_days = 14;
                break;
            case 'Monthly':
                $threshold_days = 28;
                break;
        }



        if ($sch['frequency'] === 'Once-off') {
            // Once-off tasks are never "Upcoming", they are just Due if not done (which is handled by !$last check above)
            // If we are here, $last exists, so it is completed.
            // We don't mark it as due or upcoming.
        } elseif ($diff_days >= $threshold_days) {
            $is_due = true;
        } elseif ($diff_days >= ($threshold_days - 1)) {
            $is_upcoming = true;
        }
    }

    $sch['last_completed'] = $last ? $last['completed_at'] : 'Never';
    $sch['last_staff'] = $last ? $last['staff_name'] : '-';

    if ($is_due) {
        $due_items[] = $sch;
    } elseif ($is_upcoming) {
        $upcoming_items[] = $sch;
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
        <?= APP_NAME ?> - Scheduling
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Task Scheduling</h1>
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

            <!-- Upcoming Tasks -->
            <div style="grid-column: span 2;">
                <?php if (!empty($upcoming_items)): ?>
                    <div class="glass-panel" style="border-left: 5px solid #f59e0b;">
                        <h3>⏰ Upcoming Tasks</h3>
                        <p style="font-size: 0.9rem; opacity: 0.8;">These tasks will be due within 24 hours</p>
                        <?php foreach ($upcoming_items as $item): ?>
                            <div
                                style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--card-border);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><?= h($item['name']) ?></strong>
                                        <span style="font-size: 0.8em; opacity: 0.7;">(<?= $item['frequency'] ?>)</span>
                                        <p><small><?= h($item['description']) ?></small></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create New Schedule -->
            <div>
                <div class="glass-panel">
                    <h3>Manage Schedules</h3>
                    <button onclick="document.getElementById('schedForm').style.display='block'" class="btn"
                        style="width: 100%;">+ Create Schedule</button>

                    <button
                        onclick="document.getElementById('globalHistoryModal').style.display='block'; loadGlobalHistory(5);"
                        class="btn" style="width: 100%; margin-top: 0.5rem; background: #6366f1;">📜 View Task
                        History</button>

                    <h4 style="margin-top: 1rem;">All Schedules</h4>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach (array_merge($due_items, $upcoming_items, $history) as $s): ?>
                            <li
                                style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <?= h($s['name']) ?> <small>(<?= $s['frequency'] ?>)</small>
                                </div>
                                <div>
                                    <button onclick='editSchedule(<?= json_encode($s) ?>)' class="btn"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem; margin-right: 0.25rem;">Edit</button>
                                    <button onclick='viewHistory(<?= $s["id"] ?>, "<?= h($s["name"]) ?>")' class="btn"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem; background: #6366f1;">History</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Schedule Form Modal -->
        <div id="schedForm" class="glass-panel"
            style="display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); width: 90%; max-width: 500px; z-index: 100; box-shadow: 0 0 50px rgba(0,0,0,0.5);">
            <h3>New Schedule</h3>
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
                    <option value="Once-off">Once-off</option>
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

    <!-- Edit Schedule Modal -->
    <div id="editModal" class="glass-panel"
        style="display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); width: 90%; max-width: 500px; z-index: 100; box-shadow: 0 0 50px rgba(0,0,0,0.5);">
        <h3>Edit Schedule</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_schedule">
            <input type="hidden" name="id" id="edit_id">
            <label>Task Name</label>
            <input type="text" name="name" id="edit_name" required>

            <label>Frequency</label>
            <select name="frequency" id="edit_frequency">
                <option value="Daily">Daily</option>
                <option value="Weekly">Weekly</option>
                <option value="Fortnightly">Fortnightly</option>
                <option value="Monthly">Monthly</option>
                <option value="Once-off">Once-off</option>
            </select>

            <label>Description</label>
            <textarea name="description" id="edit_description" rows="2"></textarea>

            <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'"
                    style="background: transparent; border: 1px solid var(--text-color); color: var(--text-color);">Cancel</button>
                <button type="submit" class="btn">Update</button>
            </div>
        </form>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="glass-panel"
        style="display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); width: 90%; max-width: 600px; z-index: 100; box-shadow: 0 0 50px rgba(0,0,0,0.5); max-height: 80vh; overflow-y: auto;">
        <h3>History: <span id="history_task_name"></span></h3>
        <div id="history_content">
            <!-- Populated by JavaScript -->
        </div>
        <button onclick="document.getElementById('historyModal').style.display='none'" class="btn"
            style="margin-top: 1rem;">Close</button>
    </div>

    <!-- Global History Modal -->
    <div id="globalHistoryModal" class="glass-panel"
        style="display: none; position: fixed; top: 5%; left: 50%; transform: translateX(-50%); width: 95%; max-width: 800px; z-index: 100; box-shadow: 0 0 50px rgba(0,0,0,0.5); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3>Global Task History</h3>
            <button onclick="document.getElementById('globalHistoryModal').style.display='none'"
                style="background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <div
            style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 0.5rem;">
            <label style="margin: 0;">Show last:</label>
            <select id="history_limit" onchange="loadGlobalHistory(this.value)"
                style="width: auto; margin: 0; padding: 0.25rem;">
                <option value="5">5 records</option>
                <option value="25">25 records</option>
                <option value="100">100 records</option>
            </select>
            <button onclick="loadGlobalHistory(document.getElementById('history_limit').value)" class="btn"
                style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Refresh</button>
        </div>

        <div id="global_history_content">
            <!-- Populated by JavaScript -->
        </div>
    </div>
    </div>

    <script>
        function logClean(id, name) {
            document.getElementById('log_schedule_id').value = id;
            document.getElementById('log_task_name').innerText = name;
            document.getElementById('logModal').style.display = 'block';
        }

        function editSchedule(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_frequency').value = data.frequency;
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('editModal').style.display = 'block';
        }

        function viewHistory(scheduleId, taskName) {
            document.getElementById('history_task_name').innerText = taskName;
            const content = document.getElementById('history_content');
            content.innerHTML = '<p>Loading...</p>';
            document.getElementById('historyModal').style.display = 'block';

            // Fetch history via AJAX
            fetch(`get_schedule_history.php?id=${scheduleId}`)
                .then(r => r.json())
                .then(logs => {
                    if (logs.length === 0) {
                        content.innerHTML = '<p>No completion history yet.</p>';
                        return;
                    }

                    let html = '<table style="width: 100%;"><thead><tr><th>Date</th><th>Completed By</th><th>Notes</th></tr></thead><tbody>';
                    logs.forEach(log => {
                        const date = new Date(log.completed_at).toLocaleString();
                        html += `<tr>
                            <td>${date}</td>
                            <td>${log.staff_name}</td>
                            <td>${log.notes || '-'}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    content.innerHTML = html;
                })
                .catch(err => {
                    content.innerHTML = '<p>Error loading history.</p>';
                });
        }

        function loadGlobalHistory(limit) {
            const content = document.getElementById('global_history_content');
            content.innerHTML = '<p>Loading...</p>';

            fetch(`get_all_schedule_history.php?limit=${limit}`)
                .then(r => r.json())
                .then(logs => {
                    if (logs.length === 0) {
                        content.innerHTML = '<p>No history found.</p>';
                        return;
                    }

                    let html = '<table style="width: 100%;"><thead><tr><th>Date</th><th>Task</th><th>Completed By</th><th>Notes</th></tr></thead><tbody>';
                    logs.forEach(log => {
                        const date = new Date(log.completed_at).toLocaleString();
                        html += `<tr>
                            <td>${date}</td>
                            <td><strong>${log.task_name}</strong> <small>(${log.frequency})</small></td>
                            <td>${log.staff_name}</td>
                            <td>${log.notes || '-'}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    content.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    content.innerHTML = '<p>Error loading history.</p>';
                });
        }
    </script>
</body>

</html>