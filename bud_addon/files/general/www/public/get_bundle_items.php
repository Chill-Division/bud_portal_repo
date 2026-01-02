<?php
require_once 'config.php';

$bundle_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT bi.id, bi.stock_item_id, bi.quantity, si.name, si.sku
    FROM bundle_items bi
    JOIN stock_items si ON bi.stock_item_id = si.id
    WHERE bi.bundle_id = ?
");
$stmt->execute([$bundle_id]);
$items = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($items);
