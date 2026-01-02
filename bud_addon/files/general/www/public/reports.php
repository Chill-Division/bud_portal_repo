<?php
require_once 'config.php';

$message = '';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_year = $_GET['year'] ?? date('Y');

// Handle Report Generation (Materials Out - Monthly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    // ... (Existing logic for report snapshotting can go here or remain separate)
}

// 1. DATA GATHERING

// A. Materials Out (Controlled Substances only, or all? User asked for "all materials in" but context implies controlled usually. Let's do ALL for the aggregate, and Controlled for the specific report)
// For the "Monthly Materials Out" specific report:
$start_date = "$selected_month-01";
$end_date = date("Y-m-t", strtotime($start_date));

$coc_out = $pdo->prepare("
    SELECT * FROM chain_of_custody 
    WHERE form_date BETWEEN ? AND ? 
    AND status = 'Completed'
    ORDER BY form_date ASC
");
$coc_out->execute([$start_date, $end_date]);
$transfers_out = $coc_out->fetchAll();

$report_items = [];
foreach ($transfers_out as $t) {
    $items = json_decode($t['coc_items'], true);
    if ($items) {
        foreach ($items as $item) {
            // Check if controlled (optional filtering, but we'll list all for now or filter visually)
            $report_items[] = [
                'date' => $t['form_date'],
                'destination' => $t['destination'],
                'item' => $item
            ];
        }
    }
}

// B. Materials In (Audit Log Analysis)
// Look for INSERTs on stock_items, or UPDATEs where quantity increased
$audit_in = $pdo->prepare("
    SELECT * FROM audit_log 
    WHERE table_name = 'stock_items' 
    AND action IN ('INSERT', 'UPDATE')
    AND timestamp BETWEEN ? AND ?
    ORDER BY timestamp ASC
");
// Use full timestamp range for the month
$audit_in->execute(["$start_date 00:00:00", "$end_date 23:59:59"]);
$logs_in = $audit_in->fetchAll();

$materials_in = [];
foreach ($logs_in as $log) {
    $new = json_decode($log['new_values'], true);
    $old = json_decode($log['old_values'], true);

    $qty_in = 0;

    if ($log['action'] === 'INSERT') {
        $qty_in = floatval($new['quantity'] ?? 0);
    } elseif ($log['action'] === 'UPDATE') {
        $new_qty = floatval($new['quantity'] ?? 0);
        $old_qty = floatval($old['quantity'] ?? 0);
        if ($new_qty > $old_qty) {
            $qty_in = $new_qty - $old_qty;
        }
    }

    if ($qty_in > 0) {
        $materials_in[] = [
            'date' => date('Y-m-d H:i', strtotime($log['timestamp'])),
            'name' => $new['name'] ?? 'Unknown',
            'sku' => $new['sku'] ?? '-',
            'qty' => $qty_in,
            'unit' => $new['unit'] ?? ''
        ];
    }
}

// C. 12-Month In/Out Overview
$yearly_stats = [];
for ($m = 1; $m <= 12; $m++) {
    $m_str = str_pad($m, 2, '0', STR_PAD_LEFT);
    $ym = "$selected_year-$m_str";

    // Out (COC)
    $stmt_out = $pdo->prepare("SELECT coc_items FROM chain_of_custody WHERE form_date LIKE ? AND status = 'Completed'");
    $stmt_out->execute(["$ym%"]);
    $rows_out = $stmt_out->fetchAll();

    $total_out = 0;
    foreach ($rows_out as $r) {
        $its = json_decode($r['coc_items'], true);
        foreach ($its as $i)
            $total_out += floatval($i['qty'] ?? 0);
    }

    // In (Audit)
    $stmt_in = $pdo->prepare("SELECT action, old_values, new_values FROM audit_log WHERE table_name = 'stock_items' AND timestamp LIKE ?");
    $stmt_in->execute(["$ym%"]);
    $rows_in = $stmt_in->fetchAll();

    $total_in = 0;
    foreach ($rows_in as $r) {
        $nv = json_decode($r['new_values'], true);
        $ov = json_decode($r['old_values'], true);

        if ($r['action'] === 'INSERT') {
            $total_in += floatval($nv['quantity'] ?? 0);
        } elseif ($r['action'] === 'UPDATE') {
            $nq = floatval($nv['quantity'] ?? 0);
            $oq = floatval($ov['quantity'] ?? 0);
            if ($nq > $oq)
                $total_in += ($nq - $oq);
        }
    }

    $yearly_stats[$m] = ['in' => $total_in, 'out' => $total_out];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Reports</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Reports</h1>

        <!-- Controls -->
        <div class="glass-panel" style="margin-bottom: 2rem;">
            <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end;">
                <div>
                    <label>Report Month</label>
                    <input type="month" name="month" value="<?= h($selected_month) ?>">
                </div>
                <div>
                    <label>Yearly Overview</label>
                    <select name="year">
                        <?php
                        $curr_y = date('Y');
                        for ($y = $curr_y; $y >= $curr_y - 2; $y--) {
                            $sel = $y == $selected_year ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn">Update View</button>
            </form>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 2rem;">

            <!-- Materials IN (Month) -->
            <div class="glass-panel">
                <h3>📥 Materials In (<?= date('F Y', strtotime($selected_month)) ?>)</h3>
                <p><small>Based on Inventory Logs</small></p>
                <?php if (empty($materials_in)): ?>
                    <p>No incoming materials recorded.</p>
                <?php else: ?>
                    <table style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials_in as $in): ?>
                                <tr>
                                    <td><?= h($in['date']) ?></td>
                                    <td><?= h($in['name']) ?> <small>(<?= h($in['sku']) ?>)</small></td>
                                    <td class="text-success">+<?= h($in['qty']) ?>         <?= h($in['unit']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Materials OUT (Month) -->
            <div class="glass-panel">
                <h3>📤 Materials Out (<?= date('F Y', strtotime($selected_month)) ?>)</h3>
                <p><small>Based on Completed Transfers</small></p>
                <?php if (empty($report_items)): ?>
                    <p>No outgoing transfers recorded.</p>
                <?php else: ?>
                    <table style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Dest</th>
                                <th>Item</th>
                                <th>Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_items as $out): ?>
                                <tr>
                                    <td><?= h($out['date']) ?></td>
                                    <td><?= h($out['destination']) ?></td>
                                    <td><?= h($out['item']['name']) ?></td>
                                    <td class="text-danger">-<?= h($out['item']['qty']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass-panel" style="margin-top: 2rem;">
            <h3>📊 12-Month Overview (<?= h($selected_year) ?>)</h3>
            <table style="text-align: center;">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total In</th>
                        <th>Total Out</th>
                        <th>Net Flow</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($yearly_stats as $m => $stats): ?>
                        <tr>
                            <td style="text-align: left;"><strong><?= date('F', mktime(0, 0, 0, $m, 1)) ?></strong></td>
                            <td style="color: var(--accent-color);">+<?= h($stats['in']) ?></td>
                            <td style="color: #ef4444;">-<?= h($stats['out']) ?></td>
                            <td>
                                <?php
                                $net = $stats['in'] - $stats['out'];
                                echo ($net > 0 ? '+' : '') . h($net);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>

</html>