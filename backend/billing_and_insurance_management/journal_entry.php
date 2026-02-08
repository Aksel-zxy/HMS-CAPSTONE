<?php
session_start();
include '../../SQL/config.php';

/* ===============================
   ADD JOURNAL ENTRY
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {

    $module      = $_POST['module'];
    $reference   = !empty($_POST['reference']) ? $_POST['reference'] : null;
    $description = !empty($_POST['description']) ? $_POST['description'] : null;
    $status      = $_POST['status'] ?? 'Draft';
    $created_by  = $_SESSION['username'] ?? 'Admin';

    $stmt = $conn->prepare("
        INSERT INTO journal_entries 
        (entry_date, module, description, reference, status, created_by)
        VALUES (NOW(), ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss",
        $module,
        $description,
        $reference,
        $status,
        $created_by
    );
    $stmt->execute();

    header("Location: journal_entry_line.php?entry_id=".$stmt->insert_id);
    exit;
}

/* ===============================
   FETCH RECEIPTS (ACCOUNTING SAFE)
================================ */
$receipts = $conn->query("
    SELECT 
        receipt_id,
        billing_id,
        transaction_id,
        payment_method,
        grand_total,
        status
    FROM patient_receipt
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

/* ===============================
   FILTERS
================================ */
$module_filter = $_GET['module'] ?? 'all';
$date_from = $_GET['from'] ?? null;
$date_to   = $_GET['to'] ?? null;

/* ===============================
   JOURNAL ENTRIES
================================ */
$sql = "SELECT * FROM journal_entries WHERE 1=1";
$params = [];
$types = "";

if ($module_filter !== "all") {
    $sql .= " AND module = ?";
    $params[] = $module_filter;
    $types .= "s";
}
if (!empty($date_from)) {
    $sql .= " AND entry_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $sql .= " AND entry_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$sql .= " ORDER BY entry_date DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Management Module</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">
<style>
body { background-color: #f8f9fa; }
.container-wrapper { 
    background-color: white; 
    border-radius: 30px; 
    padding: 30px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    margin-top: 80px; 
    margin-left: 100px;
}
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,.6); justify-content: center; align-items: center; }
.modal-content { background: #fff; padding: 20px; width: 500px; border-radius: 8px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; }
.form-group { margin-bottom: 15px; }
.form-group label { font-weight: 600; display: block; margin-bottom: 5px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; }
.form-group button { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
.form-group button:hover { background-color: #0056b3; }
.status.posted { color: green; font-weight: bold; }
.status.draft { color: orange; font-weight: bold; }
.badge.billing { background-color: #007bff; }
.badge.insurance { background-color: #28a745; }
.badge.supply { background-color: #6c757d; }
.badge.general { background-color: #17a2b8; }
.btn-view { background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; }
.btn-view:hover { background-color: #138496; }
.actions { margin-top: 20px; }
</style>
</head>

<body class="p-4 bg-light">

<div class="container">
<div class="container-wrapper">
    <h1 class="mb-4">Journal Management Module</h1>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-left">
            <thead class="table-white">
                <tr>
                    <th>Entry ID</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Module</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $row): ?>
                <tr>
                    <td><?= $row['entry_id'] ?></td>
                    <td><?= htmlspecialchars($row['entry_date']) ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                    <td><?= htmlspecialchars($row['reference']) ?></td>
                    <td><span class="badge <?= strtolower($row['module']) ?>"><?= ucfirst($row['module']) ?></span></td>
                    <td><?= htmlspecialchars($row['created_by']) ?></td>
                    <td><span class="status <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                    <td>
                        <a href="journal_entry_line.php?entry_id=<?= $row['entry_id'] ?>" class="btn-view">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="actions">
        <button id="openModal" class="btn btn-primary">Add Journal Entry</button>
    </div>
</div>

<div class="main-sidebar">
    <?php include 'billing_sidebar.php'; ?>
</div>
</div>

<!-- ADD ENTRY MODAL -->
<div id="addEntryModal" class="modal">
<div class="modal-content">
    <div class="modal-header">
        <h2>Add Journal Entry</h2>
        <button class="close-btn" id="closeModal">&times;</button>
    </div>

    <form method="POST">
        <input type="hidden" name="add_entry" value="1">

        <div class="form-group">
            <label>Module</label>
            <select name="module" required>
                <option value="billing">Patient Billing</option>
                <option value="insurance">Insurance</option>
                <option value="supply">Supply</option>
                <option value="general">General</option>
            </select>
        </div>

        <div class="form-group">
            <label>Reference (Receipt)</label>
            <select name="reference">
                <option value="">-- Manual Entry --</option>
                <?php foreach ($receipts as $r): ?>
                    <option value="RECEIPT-<?= $r['receipt_id'] ?>">
                        Receipt #<?= $r['receipt_id'] ?> |
                        TXN <?= $r['transaction_id'] ?> |
                        ₱<?= number_format($r['grand_total'],2) ?> |
                        <?= $r['status'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3"></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="Draft">Draft</option>
                <option value="Posted">Posted</option>
            </select>
        </div>

        <div class="form-group">
            <button type="submit">Save Entry</button>
        </div>
    </form>
</div>
</div>

<script>
const modal = document.getElementById('addEntryModal');
document.getElementById('openModal').onclick = ()=> modal.style.display='flex';
document.getElementById('closeModal').onclick = ()=> modal.style.display='none';
window.onclick = e => { if (e.target === modal) modal.style.display='none'; };
</script>

</body>
</html>
