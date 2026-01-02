<?php
require_once 'config.php';

$message = '';
$report_output = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $report_month = $_POST['month'] ?? date('Y-m', strtotime('last month'));

    if ($action === 'generate') {
        // Logic to generate report
        // 1. Get all completed COC forms for that month
        $start_date = "$report_month-01";
        $end_date = date("Y-m-t", strtotime($start_date));

        $cocs = $pdo->prepare("SELECT * FROM chain_of_custody WHERE form_date BETWEEN ? AND ? AND status = 'Completed'");
        $cocs->execute([$start_date, $end_date]);
        $rows = $cocs->fetchAll();

        // 2. Aggregate items
        $aggregated = [];

        // Helper to get item details (controlled status)
        // We'll fetch all stock items first to avoid N+1 queries
        $all_stock = $pdo->query("SELECT * FROM stock_items")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

        foreach ($rows as $row) {
            $items = json_decode($row['coc_items'], true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $sid = $item['item_id'];
                    // Only count if item exists and is controlled
                    if (isset($all_stock[$sid]) && $all_stock[$sid]['is_controlled'] == 1) {
                        if (!isset($aggregated[$sid])) {
                            $aggregated[$sid] = [
                                'name' => $all_stock[$sid]['name'],
                                'sku' => $all_stock[$sid]['sku'],
                                'unit' => $all_stock[$sid]['unit'],
                                'total_qty' => 0,
                                'batches' => []
                            ];
                        }
                        $aggregated[$sid]['total_qty'] += $item['qty'];
                        if (!empty($item['batch'])) {
                            $aggregated[$sid]['batches'][] = $item['batch']; // Keep track of batches
                        }
                    }
                }
            }
        }

        // 3. Save snapshot
        $report_data = [
            'month' => $report_month,
            'generated_at' => date('Y-m-d H:i:s'),
            'items' => array_values($aggregated)
        ];

        try {
            // Check if report already exists for this month, if so update it? Or just create new.
            // Let's create new to keep history of report generations.
            $stmt = $pdo->prepare("INSERT INTO materials_out_reports (report_month, report_data) VALUES (?, ?)");
            $stmt->execute([$report_month, json_encode($report_data)]);
            $message = "Report generated successfully.";
            $report_output = $report_data;
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Previous Reports
$saved_reports = $pdo->query("SELECT * FROM materials_out_reports ORDER BY report_month DESC, generated_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= APP_NAME ?> - Reports
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        @media print {
            body {
                background: white;
                color: black;
            }

            nav,
            .glass-panel,
            h1,
            form,
            .btn {
                display: none;
            }

            #printable-area {
                display: block !important;
            }

            .glass-panel#printable-area {
                display: block !important;
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Reports</h1>
        <?php if ($message): ?>
            <div class="glass-panel" style="margin-bottom: 1rem; border-color: var(--accent-color);">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <div class="glass-panel">
            <h3>Generate Monthly Materials Out</h3>
            <p>Generates a list of all <strong>Controlled Substances</strong> transferred out via Chain of Custody forms
                for the selected month.</p>
            <form method="POST" style="display: flex; gap: 1rem; align-items: flex-end;">
                <input type="hidden" name="action" value="generate">
                <div>
                    <label>Select Month</label>
                    <input type="month" name="month" value="<?= date('Y-m', strtotime('last month')) ?>" required>
                </div>
                <button type="submit" class="btn">Generate Report</button>
            </form>
        </div>

        <?php if ($report_output): ?>
            <div id="printable-area" class="glass-panel" style="margin-top: 2rem; background: white; color: black;">
                <h2>Materials Out Report:
                    <?= h($report_output['month']) ?>
                </h2>
                <p>Generated:
                    <?= h($report_output['generated_at']) ?>
                </p>

                <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #000;">
                            <th style="color: black; text-align: left;">SKU</th>
                            <th style="color: black; text-align: left;">Product Name</th>
                            <th style="color: black; text-align: right;">Total Qty Out</th>
                            <th style="color: black; text-align: left;">Batches Involved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_output['items'])): ?>
                            <tr>
                                <td colspan="4" style="padding: 1rem; text-align: center;">No movements of controlled substances
                                    found for this period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_output['items'] as $item): ?>
                                <tr style="border-bottom: 1px solid #ccc;">
                                    <td style="padding: 0.5rem;">
                                        <?= h($item['sku']) ?>
                                    </td>
                                    <td style="padding: 0.5rem;">
                                        <?= h($item['name']) ?>
                                    </td>
                                    <td style="padding: 0.5rem; text-align: right;">
                                        <strong>
                                            <?= h($item['total_qty']) ?>
                                        </strong>
                                        <?= h($item['unit']) ?>
                                    </td>
                                    <td style="padding: 0.5rem;">
                                        <?= implode(', ', array_unique($item['batches'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div
                    style="margin-top: 3rem; border-top: 2px solid black; padding-top: 1rem; display: flex; justify-content: space-between;">
                    <div>Signed: __________________________</div>
                    <div>Date: ________________</div>
                </div>
            </div>
            <button onclick="window.print()" class="btn" style="margin-top: 1rem;">Print / Save as PDF</button>
        <?php endif; ?>

        <div class="glass-panel" style="margin-top: 2rem;">
            <h3>Past Reports</h3>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Generated At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($saved_reports as $rep): ?>
                        <tr>
                            <td>
                                <?= h($rep['report_month']) ?>
                            </td>
                            <td>
                                <?= h($rep['generated_at']) ?>
                            </td>
                            <td>
                                <!-- We could implement a 'view saved' here similar to generate, but passing the JSON data back -->
                                <button class="btn" disabled style="opacity: 0.5; font-size: 0.8rem;">View Saved (Not
                                    Impl)</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>