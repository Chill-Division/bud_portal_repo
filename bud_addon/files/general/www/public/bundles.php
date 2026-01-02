<?php
require_once 'config.php';
require_once 'includes/audit.php';

$message = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            $name = $_POST['name'];
            $sku = $_POST['sku'] ?? '';
            $description = $_POST['description'] ?? '';

            $stmt = $pdo->prepare("INSERT INTO product_bundles (name, sku, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $sku, $description]);
            $bundle_id = $pdo->lastInsertId();

            // Add bundle items
            $item_ids = $_POST['bundle_item_id'] ?? [];
            $item_qtys = $_POST['bundle_item_qty'] ?? [];

            for ($i = 0; $i < count($item_ids); $i++) {
                if ($item_ids[$i] && $item_qtys[$i] > 0) {
                    $stmt = $pdo->prepare("INSERT INTO bundle_items (bundle_id, stock_item_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$bundle_id, $item_ids[$i], $item_qtys[$i]]);
                }
            }

            Audit::log($pdo, 'product_bundles', $bundle_id, 'INSERT', null, $_POST);
            $message = "Bundle created successfully.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'edit') {
        try {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $sku = $_POST['sku'] ?? '';
            $description = $_POST['description'] ?? '';

            // Get old data for audit
            $old_stmt = $pdo->prepare("SELECT * FROM product_bundles WHERE id = ?");
            $old_stmt->execute([$id]);
            $old = $old_stmt->fetch();

            // Update bundle
            $stmt = $pdo->prepare("UPDATE product_bundles SET name=?, sku=?, description=? WHERE id=?");
            $stmt->execute([$name, $sku, $description, $id]);

            // Delete old bundle items
            $pdo->prepare("DELETE FROM bundle_items WHERE bundle_id = ?")->execute([$id]);

            // Add new bundle items
            $item_ids = $_POST['bundle_item_id'] ?? [];
            $item_qtys = $_POST['bundle_item_qty'] ?? [];

            for ($i = 0; $i < count($item_ids); $i++) {
                if ($item_ids[$i] && $item_qtys[$i] > 0) {
                    $stmt = $pdo->prepare("INSERT INTO bundle_items (bundle_id, stock_item_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$id, $item_ids[$i], $item_qtys[$i]]);
                }
            }

            Audit::log($pdo, 'product_bundles', $id, 'UPDATE', $old, $_POST);
            $message = "Bundle updated successfully.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        try {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE product_bundles SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Bundle deleted.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch bundles and stock items
$bundles = $pdo->query("SELECT * FROM product_bundles WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$stock_items = $pdo->query("SELECT id, name, sku, unit FROM stock_items ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= APP_NAME ?> - Bundles
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Product Bundles</h1>
        <?php if ($message): ?>
            <div class="glass-panel" style="margin-bottom: 1rem; border-color: var(--accent-color);">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <button onclick="document.getElementById('addForm').style.display='block'" class="btn"
            style="margin-bottom: 1rem;">
            + Create Bundle
        </button>

        <div class="glass-panel">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>SKU</th>
                            <th>Components</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bundles as $bundle): ?>
                            <?php
                            // Fetch bundle items
                            $items_stmt = $pdo->prepare("
                                SELECT bi.quantity, si.name, si.unit
                                FROM bundle_items bi
                                JOIN stock_items si ON bi.stock_item_id = si.id
                                WHERE bi.bundle_id = ?
                            ");
                            $items_stmt->execute([$bundle['id']]);
                            $bundle_items = $items_stmt->fetchAll();
                            ?>
                            <tr>
                                <td><strong>
                                        <?= h($bundle['name']) ?>
                                    </strong></td>
                                <td>
                                    <?= h($bundle['sku']) ?>
                                </td>
                                <td>
                                    <?php foreach ($bundle_items as $item): ?>
                                        <small>
                                            <?= h($item['quantity']) ?>x
                                            <?= h($item['name']) ?>
                                        </small><br>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <button onclick='editBundle(<?= json_encode($bundle) ?>)' class="btn"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Form Modal -->
    <div id="addForm"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100; backdrop-filter: blur(5px); overflow-y: auto;">
        <div class="glass-panel" style="margin: 5vh auto; max-width: 800px; position: relative;">
            <button onclick="document.getElementById('addForm').style.display='none'"
                style="position: absolute; right: 1rem; top: 1rem; background: transparent; color: var(--text-color);">✕</button>
            <h3>New Bundle</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <label>Bundle Name</label>
                <input type="text" name="name" required>

                <label>SKU</label>
                <input type="text" name="sku">

                <label>Description</label>
                <textarea name="description" rows="2"></textarea>

                <h4>Components</h4>
                <div id="bundle-items-container">
                    <div
                        style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <select name="bundle_item_id[]">
                            <option value="">-- Select Item --</option>
                            <?php foreach ($stock_items as $item): ?>
                                <option value="<?= $item['id'] ?>">
                                    <?= h($item['name']) ?> (
                                    <?= h($item['sku']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="0.01" name="bundle_item_qty[]" placeholder="Qty" min="0">
                        <button type="button" onclick="addBundleItem()" class="btn" style="padding: 0.5rem;">+</button>
                    </div>
                </div>

                <button type="submit" class="btn" style="margin-top: 1rem;">Create Bundle</button>
            </form>
        </div>
    </div>

    <!-- Edit Form Modal -->
    <div id="editForm"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100; backdrop-filter: blur(5px); overflow-y: auto;">
        <div class="glass-panel" style="margin: 5vh auto; max-width: 800px; position: relative;">
            <button onclick="document.getElementById('editForm').style.display='none'"
                style="position: absolute; right: 1rem; top: 1rem; background: transparent; color: var(--text-color);">✕</button>
            <h3>Edit Bundle</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <label>Bundle Name</label>
                <input type="text" name="name" id="edit_name" required>

                <label>SKU</label>
                <input type="text" name="sku" id="edit_sku">

                <label>Description</label>
                <textarea name="description" id="edit_description" rows="2"></textarea>

                <h4>Components</h4>
                <div id="edit-bundle-items-container">
                    <!-- Populated by JS -->
                </div>
                <button type="button" onclick="addEditBundleItem()" class="btn" style="margin-bottom: 1rem;">+ Add
                    Item</button>

                <button type="submit" class="btn">Update Bundle</button>
            </form>
        </div>
    </div>

    <script>
        function addBundleItem() {
            const container = document.getElementById('bundle-items-container');
            const row = container.children[0].cloneNode(true);
            row.querySelectorAll('input, select').forEach(i => i.value = '');
            container.appendChild(row);
        }

        function addEditBundleItem() {
            const container = document.getElementById('edit-bundle-items-container');
            const stockOptions = <?= json_encode($stock_items) ?>;

            let optionsHtml = '<option value="">-- Select Item --</option>';
            stockOptions.forEach(item => {
                optionsHtml += `<option value="${item.id}">${item.name} (${item.sku})</option>`;
            });

            const row = document.createElement('div');
            row.style = 'display: grid; grid-template-columns: 2fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;';
            row.innerHTML = `
                <select name="bundle_item_id[]">${optionsHtml}</select>
                <input type="number" step="0.01" name="bundle_item_qty[]" placeholder="Qty" min="0">
            `;
            container.appendChild(row);
        }

        function editBundle(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_sku').value = data.sku;
            document.getElementById('edit_description').value = data.description;

            // Fetch bundle items via PHP
            fetch(`get_bundle_items.php?id=${data.id}`)
                .then(r => r.json())
                .then(items => {
                    const container = document.getElementById('edit-bundle-items-container');
                    container.innerHTML = '';

                    const stockOptions = <?= json_encode($stock_items) ?>;

                    items.forEach(item => {
                        let optionsHtml = '<option value="">-- Select Item --</option>';
                        stockOptions.forEach(stock => {
                            const selected = stock.id == item.stock_item_id ? 'selected' : '';
                            optionsHtml += `<option value="${stock.id}" ${selected}>${stock.name} (${stock.sku})</option>`;
                        });

                        const row = document.createElement('div');
                        row.style = 'display: grid; grid-template-columns: 2fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;';
                        row.innerHTML = `
                            <select name="bundle_item_id[]">${optionsHtml}</select>
                            <input type="number" step="0.01" name="bundle_item_qty[]" value="${item.quantity}" min="0">
                        `;
                        container.appendChild(row);
                    });
                });

            document.getElementById('editForm').style.display = 'block';
        }
    </script>
</body>

</html>