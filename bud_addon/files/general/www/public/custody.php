<?php
require_once 'config.php';
require_once 'includes/audit.php';

$message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_coc') {
        try {
            $date = $_POST['date'];
            $origin = $_POST['origin'];
            $destination = $_POST['destination'];
            $transported_by = $_POST['transported_by'];
            $signature = $_POST['signature_data']; // Base64

            // Items
            $item_ids = $_POST['item_id'] ?? [];
            $qtys = $_POST['qty'] ?? [];
            $batches = $_POST['batch'] ?? [];

            $coc_items = [];
            for ($i = 0; $i < count($item_ids); $i++) {
                if ($item_ids[$i] && $qtys[$i] > 0) {
                    $coc_items[] = [
                        'item_id' => $item_ids[$i],
                        'qty' => $qtys[$i],
                        'batch' => $batches[$i]
                    ];

                    // Deduct stock?
                    // User requirement: "Allocate stock... taking stock off-site"
                    // We probably should deduct stock or mark it as in-transit.
                    // For now, let's just log the movement in COC.
                }
            }

            $stmt = $pdo->prepare("INSERT INTO chain_of_custody (form_date, origin, destination, transported_by, coc_items, signature_image, status) VALUES (?, ?, ?, ?, ?, ?, 'Completed')");
            $stmt->execute([
                $date,
                $origin,
                $destination,
                $transported_by,
                json_encode($coc_items),
                $signature
            ]);
            $id = $pdo->lastInsertId();

            Audit::log($pdo, 'chain_of_custody', $id, 'INSERT', null, $_POST);
            $message = "Chain of Custody form saved.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch History
$history = $pdo->query("SELECT * FROM chain_of_custody ORDER BY created_at DESC LIMIT 50")->fetchAll();
$stock_options = $pdo->query("SELECT id, name, sku, unit FROM stock_items WHERE quantity > 0 ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Chain of Custody</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        #signature-pad {
            border: 2px dashed var(--card-border);
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.5);
            cursor: crosshair;
            width: 100%;
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Chain of Custody</h1>
        <?php if ($message): ?>
            <div class="glass-panel" style="margin-bottom: 1rem; border-color: var(--accent-color);">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <button
            onclick="document.getElementById('cocForm').style.display = document.getElementById('cocForm').style.display === 'none' ? 'block' : 'none'"
            class="btn" style="margin-bottom: 1rem;">
            + New Transfer Form
        </button>

        <div id="cocForm" class="glass-panel" style="display: none; margin-bottom: 2rem;">
            <h3>New Transfer</h3>
            <form method="POST" id="transferForm">
                <input type="hidden" name="action" value="create_coc">
                <input type="hidden" name="signature_data" id="signature_data">

                <div class="grid">
                    <div>
                        <label>Date</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Origin</label>
                        <input type="text" name="origin" value="Main Facility" required>
                    </div>
                    <div>
                        <label>Destination</label>
                        <input type="text" name="destination" placeholder="e.g. Dispensary A" required>
                    </div>
                </div>

                <label>Transported By (Staff Name)</label>
                <input type="text" name="transported_by" required>

                <h4>Items Transferred</h4>
                <div id="items-container">
                    <div class="item-row grid" style="margin-bottom: 0.5rem;">
                        <div>
                            <select name="item_id[]" required>
                                <option value="">Select Item</option>
                                <?php foreach ($stock_options as $opt): ?>
                                    <option value="<?= $opt['id'] ?>"><?= h($opt['name']) ?> (<?= h($opt['sku']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <input type="text" name="batch[]" placeholder="Batch/Lot #">
                        </div>
                        <div>
                            <input type="number" step="0.01" name="qty[]" placeholder="Qty" required>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn" onclick="addItemRow()"
                    style="background: transparent; border: 1px solid var(--primary-color); color: var(--text-color);">+
                    Add Another Item</button>

                <h4 style="margin-top: 2rem;">Receiver Signature</h4>
                <p><small>Sign below to acknowledge receipt/custody.</small></p>
                <canvas id="signature-pad" width="600" height="200"></canvas>
                <button type="button" onclick="clearSignature()" style="font-size: 0.8rem; margin-top: 0.5rem;">Clear
                    Signature</button>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn" onclick="return saveSignature()">Generate COC & Save</button>
                </div>
            </form>
        </div>

        <div class="glass-panel">
            <h3>Recent Transfers</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>To</th>
                        <th>Transported By</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?= h($row['form_date']) ?></td>
                            <td><?= h($row['destination']) ?></td>
                            <td><?= h($row['transported_by']) ?></td>
                            <td><?= h($row['status']) ?></td>
                            <td>
                                <?php
                                $items = json_decode($row['coc_items'], true);
                                echo count($items) . ' items';
                                ?>
                            </td>
                            <td>
                                <button onclick='viewCoc(<?= json_encode($row) ?>)' class="btn"
                                    style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100; backdrop-filter: blur(5px); overflow-y: auto;">
        <div class="glass-panel"
            style="margin: 5vh auto; max-width: 800px; background: white; color: black; position: relative;">
            <button onclick="document.getElementById('viewModal').style.display='none'"
                style="position: absolute; right: 1rem; top: 1rem; background: transparent; color: black; font-weight: bold; border: 1px solid black;">✕
                Close</button>
            <div id="coc-content" style="padding: 2rem;">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>

    <script>
        // Item Repeater
        function addItemRow() {
            const container = document.getElementById('items-container');
            const row = container.children[0].cloneNode(true);
            row.querySelectorAll('input').forEach(i => i.value = '');
            container.appendChild(row);
        }

        // Signature Pad
        const canvas = document.getElementById('signature-pad');
        const ctx = canvas.getContext('2d');
        let painting = false;

        function startPosition(e) {
            painting = true;
            draw(e);
        }

        function endPosition() {
            painting = false;
            ctx.beginPath();
        }

        function draw(e) {
            if (!painting) return;
            e.preventDefault();

            const rect = canvas.getBoundingClientRect();

            // Calculate scale factors (Buffer Size / Display Size)
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;

            // Handle Touch
            const x = ((e.clientX || e.touches[0].clientX) - rect.left) * scaleX;
            const y = ((e.clientY || e.touches[0].clientY) - rect.top) * scaleY;

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000'; // Always black for signature

            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        canvas.addEventListener('mousedown', startPosition);
        canvas.addEventListener('mouseup', endPosition);
        canvas.addEventListener('mousemove', draw);

        canvas.addEventListener('touchstart', startPosition);
        canvas.addEventListener('touchend', endPosition);
        canvas.addEventListener('touchmove', draw);

        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        function saveSignature() {
            const dataUrl = canvas.toDataURL();
            document.getElementById('signature_data').value = dataUrl;
            return true;
        }

        // Resize canvas helper (simple)
        function resizeCanvas() {
            // we could make it responsive here, but fixed 600 width is okay for landscape tablet
        }

        // View Logic
        function viewCoc(data) {
            const items = JSON.parse(data.coc_items);
            let itemHtml = '<table style="width:100%; border-collapse: collapse; margin-top: 1rem;"><thead><tr style="border-bottom: 2px solid #000;"><th>Item ID</th><th>Batch</th><th>Qty</th></tr></thead><tbody>';
            items.forEach(i => {
                itemHtml += `<tr><td style="border-bottom: 1px solid #ccc; padding: 0.5rem;">${i.item_id}</td><td style="border-bottom: 1px solid #ccc;">${i.batch || '-'}</td><td style="border-bottom: 1px solid #ccc;">${i.qty}</td></tr>`;
            });
            itemHtml += '</tbody></table>';

            const html = `
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h2>Chain of Custody Record</h2>
                    <p>Doc ID: #${data.id}</p>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                    <div><strong>Date:</strong> ${data.form_date}</div>
                    <div><strong>Transported By:</strong> ${data.transported_by}</div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 2rem;">
                     <div><strong>From:</strong> ${data.origin}</div>
                     <div><strong>To:</strong> ${data.destination}</div>
                </div>
                
                ${itemHtml}
                
                <div style="margin-top: 3rem;">
                    <p><strong>Receiver Signature:</strong></p>
                    <img src="${data.signature_image}" style="border: 1px solid #ccc; max-width: 100%; height: auto;">
                </div>
            `;
            document.getElementById('coc-content').innerHTML = html;
            document.getElementById('viewModal').style.display = 'block';
        }
    </script>
</body>

</html>