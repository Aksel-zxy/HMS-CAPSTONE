<?php
session_start();
require 'db.php';

// Show errors (for debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ Fetch user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("⚠️ User not found in database.");
}

$department = !empty($user['department']) ? $user['department'] : 'Unknown Department';
$department_id = !empty($user['department_id']) ? $user['department_id'] : 0;

// ✅ Handle manual form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $month = date('Y-m');
        $items = $_POST['items'] ?? [];
        $items_json = json_encode($items, JSON_UNESCAPED_UNICODE);
        $total_items = count($items);

        $stmt = $pdo->prepare("INSERT INTO department_request 
            (user_id, department, department_id, month, items, total_items, status)
            VALUES (:user_id, :department, :department_id, :month, :items, :total_items, 'Pending')");
        $stmt->execute([
            ':user_id' => $user_id,
            ':department' => $department,
            ':department_id' => $department_id,
            ':month' => $month,
            ':items' => $items_json,
            ':total_items' => $total_items
        ]);

        $success = "✅ Request successfully submitted!";
    } catch (PDOException $e) {
        $error = "❌ Error submitting request: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Request Form</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    background: #f8fafc;
    font-family: 'Segoe UI', sans-serif;
}
.card {
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    border-radius: 12px;
}
.table th {
    background: #f1f5f9;
}
.btn-remove {
    color: #dc3545;
    border: none;
    background: transparent;
}
.btn-remove:hover {
    color: #b91c1c;
}
</style>
</head>
<body class="p-4">

<div class="container">
    <div class="card p-4">
        <h2 class="text-center mb-4 text-primary">📋 Department Request Form</h2>

        <div class="alert alert-info">
            <strong>Department:</strong> <?= htmlspecialchars($department) ?><br>
            <strong>Month:</strong> <?= date('F Y') ?>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php elseif(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" id="manualForm">
            <div class="table-responsive mb-3">
                <table class="table table-bordered align-middle" id="itemTable">
                    <thead class="text-center">
                        <tr>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemBody">
                        <tr>
                            <td><input type="text" name="items[0][name]" class="form-control" placeholder="Enter item name" required></td>
                            <td><input type="text" name="items[0][description]" class="form-control" placeholder="Enter description"></td>
                            <td><input type="number" name="items[0][quantity]" class="form-control" min="1" value="1" required></td>
                            <td class="text-center"><button type="button" class="btn-remove"><i class="bi bi-x-circle"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="text-center mb-4">
                <button type="button" class="btn btn-outline-primary" id="addRowBtn">
                    <i class="bi bi-plus-circle"></i> Add Another Item
                </button>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-send"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let itemIndex = 1;

document.getElementById('addRowBtn').addEventListener('click', () => {
    const tbody = document.getElementById('itemBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="items[${itemIndex}][name]" class="form-control" placeholder="Enter item name" required></td>
        <td><input type="text" name="items[${itemIndex}][description]" class="form-control" placeholder="Enter description"></td>
        <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control" min="1" value="1" required></td>
        <td class="text-center"><button type="button" class="btn-remove"><i class="bi bi-x-circle"></i></button></td>
    `;
    tbody.appendChild(row);
    itemIndex++;
});

// Remove row
document.addEventListener('click', e => {
    if (e.target.closest('.btn-remove')) {
        e.target.closest('tr').remove();
    }
});
</script>
</body>
</html>
