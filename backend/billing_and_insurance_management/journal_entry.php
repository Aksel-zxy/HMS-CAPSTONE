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

<link rel="stylesheet" href="assets/CSS/journalentry.css">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">

<style>
.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.6);justify-content:center;align-items:center}
.modal-content{background:#fff;padding:20px;width:500px;border-radius:8px}
.status.posted{color:green;font-weight:bold}
.status.draft{color:orange;font-weight:bold}
.badge.billing{background:#007bff;color:#fff}
.badge.insurance{background:#28a745;color:#fff}
.badge.supply{background:#6c757d;color:#fff}
.badge.general{background:#17a2b8;color:#fff}
</style>
</head>

<body>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container">
<h1>Journal Management Module</h1>

<table class="table table-bordered table-striped">
<thead>
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
<a href="journal_entry_line.php?entry_id=<?= $row['entry_id'] ?>" class="btn-view">View Lines</a>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<button id="openModal" class="btn btn-primary">Add Journal Entry</button>
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

<button type="submit">Save Entry</button>
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
