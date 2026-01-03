<?php
require_once 'config.php';
require_once 'includes/audit.php';

$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            $stmt = $pdo->prepare("INSERT INTO stock_items (supplier_id, name, sku, category, description, quantity, unit, reorder_level, is_controlled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['supplier_id'],
                $_POST['name'],
                $_POST['sku'],
                $_POST['category'],
                $_POST['description'],
                $_POST['quantity'],
                $_POST['unit'],
                $_POST['reorder_level'],
                isset($_POST['is_controlled']) ? 1 : 0
            ]);
            $id = $pdo->lastInsertId();
            Audit::log($pdo, 'stock_items', $id, 'INSERT', null, $_POST);
            $message = "Stock item added.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        // Fetch old
        $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();

        if ($old) {
            $stmt = $pdo->prepare("UPDATE stock_items SET supplier_id=?, name=?, sku=?, category=?, description=?, quantity=?, unit=?, reorder_level=?, is_controlled=? WHERE id=?");
            $stmt->execute([
                $_POST['supplier_id'],
                $_POST['name'],
                $_POST['sku'],
                $_POST['category'],
                $_POST['description'],
                $_POST['quantity'],
                $_POST['unit'],
                $_POST['reorder_level'],
                isset($_POST['is_controlled']) ? 1 : 0,
                $id
            ]);
            Audit::log($pdo, 'stock_items', $id, 'UPDATE', $old, $_POST);
            $message = "Stock item updated.";
        }
    } elseif ($action === 'adjust_stock') {
        $id = $_POST['item_id'] ?? 0;
        $operation = $_POST['operation'] ?? ''; // 'add' or 'subtract'
        $quantity = floatval($_POST['quantity'] ?? 0);
        $notes = $_POST['notes'] ?? '';

        if ($quantity <= 0) {
            $message = "Error: Quantity must be greater than 0.";
        } else {
            // Fetch current item
            $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if ($item) {
                // Validation for subtract
                if ($operation === 'subtract') {
                    if ($item['is_controlled']) {
                        $message = "Error: Controlled substances must be transferred via Chain of Custody.";
                    } elseif ($item['quantity'] < $quantity) {
                        $message = "Error: Cannot subtract more than available stock (" . $item['quantity'] . " " . $item['unit'] . " available).";
                    } else {
                        // Perform subtraction
                        $new_qty = $item['quantity'] - $quantity;
                        $stmt = $pdo->prepare("UPDATE stock_items SET quantity = ? WHERE id = ?");
                        $stmt->execute([$new_qty, $id]);

                        // Audit log
                        $new_data = $item;
                        $new_data['quantity'] = $new_qty;
                        $new_data['adjustment_notes'] = $notes;
                        Audit::log($pdo, 'stock_items', $id, 'UPDATE', $item, $new_data);
                        $message = "Stock subtracted successfully. " . $quantity . " " . $item['unit'] . " removed.";
                    }
                } elseif ($operation === 'add') {
                    // Perform addition
                    $new_qty = $item['quantity'] + $quantity;
                    $stmt = $pdo->prepare("UPDATE stock_items SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_qty, $id]);

                    // Audit log
                    $new_data = $item;
                    $new_data['quantity'] = $new_qty;
                    $new_data['adjustment_notes'] = $notes;
                    Audit::log($pdo, 'stock_items', $id, 'UPDATE', $item, $new_data);
                    $message = "Stock added successfully. " . $quantity . " " . $item['unit'] . " added.";
                }
            } else {
                $message = "Error: Item not found.";
            }
        }
    }
}

// Fetch Data
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$stock = $pdo->query("
    SELECT s.*, sup.name as supplier_name 
    FROM stock_items s 
    LEFT JOIN suppliers sup ON s.supplier_id = sup.id 
    ORDER BY s.name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= APP_NAME ?> - Stock
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Stock Inventory</h1>
        <?php if ($message): ?>
            <div class="glass-panel" style="margin-bottom: 1rem; border-color: var(--accent-color);">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <button
            onclick="document.getElementById('addForm').style.display = document.getElementById('addForm').style.display === 'none' ? 'block' : 'none'"
            class="btn" style="margin-bottom: 1rem;">
            + Add Stock Item
        </button>

        <a href="bundles.php" class="btn" style="margin-bottom: 1rem; margin-left: 0.5rem; background: #6366f1;">
            📦 Manage Bundles
        </a>

        <div id="addForm" class="glass-panel" style="display: none; margin-bottom: 2rem;">
            <h3>New Stock Item</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="grid">
                    <div>
                        <label>Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div>
                        <label>SKU</label>
                        <input type="text" name="sku">
                    </div>
                    <div>
                        <label>Supplier</label>
                        <select name="supplier_id">
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>">
                                    <?= h($sup['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Category <span class="help-icon"
                                title="Group items for easier reporting (e.g. Raw Materials vs Finished Products)">?</span></label>
                        <select name="category">
                            <option value="Raw Material">Raw Material</option>
                            <option value="Finished Product">Finished Product</option>
                            <option value="Packaging">Packaging</option>
                            <option value="Sticker">Sticker</option>
                            <option value="Insert">Insert</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Quantity</label>
                        <input type="number" step="0.01" name="quantity" required>
                    </div>
                    <div>
                        <label>Unit</label>
                        <input type="text" name="unit" placeholder="e.g. kg, units">
                    </div>
                    <div>
                        <label>Reorder Level <span class="help-icon"
                                title="Minimum quantity before the item is flagged as Low Stock">?</span></label>
                        <input type="number" step="0.01" name="reorder_level">
                    </div>
                    <div style="display: flex; align-items: center; margin-top: 2rem;">
                        <input type="checkbox" name="is_controlled" id="is_controlled_new"
                            style="width: auto; margin-right: 0.5rem;">
                        <label for="is_controlled_new" style="cursor: pointer;">Controlled Substance?</label>
                    </div>
                </div>

                <label>Description</label>
                <textarea name="description" rows="2"></textarea>

                <button type="submit" class="btn">Save Item</button>
            </form>
        </div>

        <div class="glass-panel">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Qty</th>
                            <th>Ctrl?</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock as $item): ?>
                            <tr
                                style="<?= $item['quantity'] <= $item['reorder_level'] ? 'background: rgba(239, 68, 68, 0.1);' : '' ?>">
                                <td><small>
                                        <?= h($item['sku']) ?>
                                    </small></td>
                                <td><strong>
                                        <?= h($item['name']) ?>
                                    </strong></td>
                                <td>
                                    <?= h(floatval($item['quantity'])) ?>
                                    <?= h($item['unit']) ?>
                                </td>
                                <td>
                                    <?= $item['is_controlled'] ? '⚠️' : '' ?>
                                </td>
                                <td>
                                    <button onclick='addStock(<?= json_encode($item) ?>)' class="btn"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #10b981; margin: 0.1rem;">+
                                        Add</button>
                                    <?php if (!$item['is_controlled']): ?>
                                        <button onclick='subtractStock(<?= json_encode($item) ?>)' class="btn"
                                            style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #ef4444; margin: 0.1rem;">-
                                            Remove</button>
                                    <?php endif; ?>
                                    <button onclick='editStock(<?= json_encode($item) ?>)' class="btn"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem; margin: 0.1rem;">Edit</button>
                                    <button onclick='viewHistory(<?= $item['id'] ?>, "<?= addslashes($item['name']) ?>")'
                                        class="btn"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #6366f1; margin: 0.1rem;">📜</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div id="adjustModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 101; backdrop-filter: blur(5px);">
        <div class="glass-panel" style="margin: 15vh auto; max-width: 400px; position: relative;">
            <button onclick="document.getElementById('adjustModal').style.display='none'"
                style="position: absolute; right: 1rem; top: 1rem; background: transparent; color: var(--text-color);">✕</button>
            <h3 id="adjust_title">Adjust Stock</h3>
            <p id="adjust_item_display"></p>
            <form method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="item_id" id="adjust_item_id">
                <input type="hidden" name="operation" id="adjust_operation">

                <label>Quantity to <span id="adjust_op_label"></span></label>
                <input type="number" step="0.01" name="quantity" min="0.01" required>

                <label>Notes</label>
                <textarea name="notes" rows="2" placeholder="e.g. Damage, Restock..."></textarea>

                <button type="submit" class="btn" id="adjust_submit_btn">Confirm</button>
            </form>
        </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 102; backdrop-filter: blur(5px);">
        <div class="glass-panel" style="margin: 5vh auto; max-width: 600px; position: relative;">
            <button onclick="document.getElementById('historyModal').style.display='none'"
                style="position: absolute; right: 1rem; top: 1rem; background: transparent; color: var(--text-color);">✕</button>
            <h3>Stock History: <span id="history_item_name"></span></h3>
            <div id="history_content" style="max-height: 60vh; overflow-y: auto; margin-top: 1rem;">
                <p>Loading history...</p>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; backdrop-filter: blur(5px); overflow-y: auto;">
        <div class="glass-panel" style="margin: 5vh auto; max-width: 800px; position: relative;">
            <button onclick="document.getElementById('editModal').style.display='none'"
                style="position: absolute; right: 1rem; top: 1rem; background: transparent; color: var(--text-color);">✕</button>
            <h3>Edit Stock Item</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="grid">
                    <div>
                        <label>Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div>
                        <label>SKU</label>
                        <input type="text" name="sku" id="edit_sku">
                    </div>
                    <div>
                        <label>Supplier</label>
                        <select name="supplier_id" id="edit_supplier_id">
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>">
                                    <?= h($sup['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Category</label>
                        <select name="category" id="edit_category">
                            <option value="Raw Material">Raw Material</option>
                            <option value="Finished Product">Finished Product</option>
                            <option value="Packaging">Packaging</option>
                            <option value="Sticker">Sticker</option>
                            <option value="Insert">Insert</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Quantity</label>
                        <input type="number" step="0.01" name="quantity" id="edit_quantity" required>
                    </div>
                    <div>
                        <label>Unit</label>
                        <input type="text" name="unit" id="edit_unit">
                    </div>
                    <div>
                        <label>Reorder Level</label>
                        <input type="number" step="0.01" name="reorder_level" id="edit_reorder_level">
                    </div>
                    <div style="display: flex; align-items: center; margin-top: 2rem;">
                        <input type="checkbox" name="is_controlled" id="edit_is_controlled"
                            style="width: auto; margin-right: 0.5rem;">
                        <label for="edit_is_controlled" style="cursor: pointer;">Controlled Substance?</label>
                    </div>
                </div>

                <label>Description</label>
                <textarea name="description" id="edit_description" rows="2"></textarea>

                <button type="submit" class="btn">Update Item</button>
            </form>
        </div>
    </div>

    <script>
        function editStock(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_sku').value = data.sku;
            document.getElementById('edit_supplier_id').value = data.supplier_id;
            document.getElementById('edit_category').value = data.category;
            document.getElementById('edit_quantity').value = data.quantity;
            document.getElementById('edit_unit').value = data.unit;
            document.getElementById('edit_reorder_level').value = data.reorder_level;
            document.getElementById('edit_description').value = data.description;
            document.getElementById('edit_is_controlled').checked = data.is_controlled == 1;

            document.getElementById('editModal').style.display = 'block';
        }

        function addStock(data) {
            document.getElementById('adjust_title').innerText = 'Add Stock';
            document.getElementById('adjust_op_label').innerText = 'add';
            document.getElementById('adjust_operation').value = 'add';
            document.getElementById('adjust_item_id').value = data.id;
            document.getElementById('adjust_item_display').innerText = data.name + ' (' + data.quantity + ' ' + data.unit + ' current)';
            document.getElementById('adjust_submit_btn').style.background = '#10b981';
            document.getElementById('adjustModal').style.display = 'block';
        }

        function subtractStock(data) {
            document.getElementById('adjust_title').innerText = 'Remove Stock';
            document.getElementById('adjust_op_label').innerText = 'remove';
            document.getElementById('adjust_operation').value = 'subtract';
            document.getElementById('adjust_item_id').value = data.id;
            document.getElementById('adjust_item_display').innerText = data.name + ' (' + data.quantity + ' ' + data.unit + ' current)';
            document.getElementById('adjust_submit_btn').style.background = '#ef4444';
            document.getElementById('adjustModal').style.display = 'block';
        }

        function viewHistory(id, name) {
            document.getElementById('history_item_name').innerText = name;
            document.getElementById('history_content').innerHTML = '<p>Loading history...</p>';
            document.getElementById('historyModal').style.display = 'block';

            fetch('get_stock_history.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.length === 0) {
                        document.getElementById('history_content').innerHTML = '<p>No history found for this item.</p>';
                        return;
                    }
                    let html = '<table style="font-size: 0.85rem;"><thead><tr><th>Date</th><th>Type</th><th>Change</th><th>Total</th><th>Notes</th></tr></thead><tbody>';
                    data.forEach(row => {
                        let diffColor = row.diff > 0 ? '#10b981' : (row.diff < 0 ? '#ef4444' : 'inherit');
                        let diffSign = row.diff > 0 ? '+' : '';
                        html += `<tr>
                            <td><small>${row.timestamp}</small></td>
                            <td>${row.type}</td>
                            <td style="color: ${diffColor}; font-weight: bold;">${diffSign}${row.diff}</td>
                            <td>${row.qty_new}</td>
                            <td><small>${row.notes}</small></td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    document.getElementById('history_content').innerHTML = html;
                })
                .catch(e => {
                    document.getElementById('history_content').innerHTML = '<p>Error loading history.</p>';
                });
        }
    </script>
</body>

</html>