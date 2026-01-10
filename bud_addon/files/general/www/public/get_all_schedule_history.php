<?php
require_once 'config.php';

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
// Sanity check limit
if ($limit < 1 || $limit > 100)
    $limit = 5;

$stmt = $pdo->prepare("
    SELECT 
        l.completed_at,
        l.staff_name,
        l.notes,
        s.name as task_name,
        s.frequency
    FROM cleaning_logs l
    LEFT JOIN cleaning_schedules s ON l.schedule_id = s.id
    ORDER BY l.completed_at DESC
    LIMIT ?
");

$stmt->execute([$limit]);
$history = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($history);
?>