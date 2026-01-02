<?php
require_once 'config.php';
require_once 'includes/audit.php';

$message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = $_POST['name'] ?? '';
        $contact = $_POST['contact'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $notes = $_POST['notes'] ?? '';

        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $contact, $email, $phone, $address, $notes]);
                $id = $pdo->lastInsertId();

                Audit::log($pdo, 'suppliers', $id, 'INSERT', null, $_POST);
                $message = "Supplier created successfully.";
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $contact = $_POST['contact'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $notes = $_POST['notes'] ?? '';

        // Fetch old values for audit
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();

        if ($old) {
            $stmt = $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, email=?, phone=?, address=?, notes=? WHERE id=?");
            $stmt->execute([$name, $contact, $email, $phone, $address, $notes, $id]);

            Audit::log($pdo, 'suppliers', $id, 'UPDATE', $old, $_POST);
            $message = "Supplier updated successfully.";
        }
    }
}

// Fetch Suppliers
$suppliers = $pdo->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= APP_NAME ?> - Suppliers
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>Suppliers</h1>
        <?php if ($message): ?>
            <div class="glass-panel"
                style="margin-bottom: 1rem; color: var(--accent-color); border-color: var(--accent-color);">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <!-- Simple Toggle for Add Form -->
        <button
            onclick="document.getElementById('addForm').style.display = document.getElementById('addForm').style.display === 'none' ? 'block' : 'none'"
            class="btn" style="margin-bottom: 1rem;">
            + Add New Supplier
        </button>

        <div id="addForm" class="glass-panel" style="display: none; margin-bottom: 2rem;">
            <h3>New Supplier</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="grid">
                    <div>
                        <label>Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div>
                        <label>Contact Person</label>
                        <input type="text" name="contact">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div>
                        <label>Phone</label>
                        <input type="text" name="phone">
                    </div>
                </div>
                <label>Address</label>
                <textarea name="address" rows="2"></textarea>

                <label>Notes</label>
                <textarea name="notes" rows="2"></textarea>

                <button type="submit" class="btn">Save Supplier</button>
            </form>
        </div>

        <div class="glass-panel">
            <div class="table-responsive">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Email / Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td><strong>
                                        <?= h($s['name']) ?>
                                    </strong></td>
                                <td>
                                    <?= h($s['contact_person']) ?>
                                </td>
                                <td>
                                    <?= h($s['email']) ?><br>
                                    <small>
                                        <?= h($s['phone']) ?>
                                    </small>
                                </td>
                                <td>
                                    <button onclick='editSupplier(<?= json_encode($s) ?>)' class="btn"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal (Simplified as a hidden form that populates) -->
    <div id="editModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; backdrop-filter: blur(5px);">
        <div class="glass-panel" style="margin: 10% auto; max-width: 600px; position: relative;">
            <button onclick="document.getElementById('editModal').style.display='none'"
                style="position: absolute; right: 1rem; top: 1rem; background: transparent; color: var(--text-color);">✕</button>
            <h3>Edit Supplier</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="grid">
                    <div>
                        <label>Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div>
                        <label>Contact Person</label>
                        <input type="text" name="contact" id="edit_contact">
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email">
                    </div>
                    <div>
                        <label>Phone</label>
                        <input type="text" name="phone" id="edit_phone">
                    </div>
                </div>

                <label>Address</label>
                <textarea name="address" id="edit_address" rows="2"></textarea>

                <label>Notes</label>
                <textarea name="notes" id="edit_notes" rows="2"></textarea>

                <button type="submit" class="btn">Update Supplier</button>
            </form>
        </div>
    </div>

    <script>
        function editSupplier(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_contact').value = data.contact_person;
            document.getElementById('edit_email').value = data.email;
            document.getElementById('edit_phone').value = data.phone;
            document.getElementById('edit_address').value = data.address;
            document.getElementById('edit_notes').value = data.notes;

            document.getElementById('editModal').style.display = 'block';
        }
    </script>
</body>

</html>