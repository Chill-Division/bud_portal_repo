<?php
require_once 'config.php';

$schedule_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT staff_name, completed_at, notes 
    FROM cleaning_logs 
    WHERE schedule_id = ? 
    ORDER BY completed_at DESC 
    LIMIT 7
");
$stmt->execute([$schedule_id]);
$logs = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($logs);
