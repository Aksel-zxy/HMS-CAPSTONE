<?php
session_start();
include '../../SQL/config.php';

// ✅ Handle Add Journal Entry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $module = $_POST['module'];
    $reference = $_POST['reference'] ?: null;
    $description = $_POST['description'] ?: null;
    $status = $_POST['status'] ?? 'Draft';
    $created_by = $_SESSION['username'] ?? "Admin";

    $stmt = $conn->prepare("
        INSERT INTO journal_entries (entry_date, module, description, reference, status, created_by) 
        VALUES (NOW(), ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", $module, $description, $reference, $status, $created_by);
    $stmt->execute();
    $entry_id = $stmt->insert_id;

    header("Location: journal_entry_line.php?entry_id=" . $entry_id);
    exit;
}

// ✅ Handle Delete Journal Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry'])) {
    $entry_id = (int)$_POST['entry_id'];
    $stmt = $conn->prepare("DELETE FROM journal_entries WHERE entry_id = ?");
    $stmt->bind_param("i", $entry_id);
    $stmt->execute();
    header("Location: journal_entry.php");
    exit;
}

// ✅ Handle Edit Journal Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_entry'])) {
    $entry_id = (int)$_POST['entry_id'];
    $module = $_POST['module'];
    $reference = $_POST['reference'] ?: null;
    $description = $_POST['description'] ?: null;
    $status = $_POST['status'] ?? 'Draft';

    $stmt = $conn->prepare("
        UPDATE journal_entries 
        SET module=?, reference=?, description=?, status=? 
        WHERE entry_id=?
    ");
    $stmt->bind_param("ssssi", $module, $reference, $description, $status, $entry_id);
    $stmt->execute();
    header("Location: journal_entry.php");
    exit;
}

// ✅ Fetch receipts for dropdown
$receipts = $conn->query("
    SELECT receipt_id, transaction_id, payment_method, grand_total, status 
    FROM patient_receipt 
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// ✅ Filters
$module_filter = $_GET['module'] ?? 'all';
$date_from = $_GET['from'] ?? null;
$date_to = $_GET['to'] ?? null;

// ✅ Base query
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
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$entries = $result->fetch_all(MYSQLI_ASSOC);

// ✅ Count totals
$total_entries = count($entries);
$total_posted = count(array_filter($entries, fn($e) => $e['status'] === 'Posted'));
$total_draft = $total_entries - $total_posted;

// Build a minimal entries map for JS (id => needed fields)
$__entries_map = [];
foreach ($entries as $e) {
    $__entries_map[$e['entry_id']] = [
        'module' => $e['module'],
        'reference' => $e['reference'],
        'description' => $e['description'],
        'status' => $e['status']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Management Module</title>
<link rel="stylesheet" href="assets/CSS/journalentry.css">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
<style>
/* Basic styling and dropdown/modal CSS */
.modal { display: none; position: fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; }
.modal-content { background:#fff; padding:20px; width:500px; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.3); }
.modal-header { display:flex; justify-content:space-between; align-items:center; }
.close-btn { background:none; border:none; font-size:20px; cursor:pointer; }
.form-group { margin-bottom:15px; }
.form-group label { display:block; margin-bottom:5px; }
.form-group input, .form-group select, .form-group textarea { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; }
.status.posted { color: green; font-weight:bold; }
.status.draft { color: orange; font-weight:bold; }
.badge.billing { background:#007bff; color:#fff; padding:2px 6px; border-radius:4px; }
.badge.insurance { background:#28a745; color:#fff; padding:2px 6px; border-radius:4px; }
.badge.supply { background:#6c757d; color:#fff; padding:2px 6px; border-radius:4px; }
.badge.general { background:#17a2b8; color:#fff; padding:2px 6px; border-radius:4px; }
.dropdown-actions { position: relative; display:inline-block; }
.dropdown-menu { display:none; position:absolute; right:0; background:#fff; border:1px solid #ccc; box-shadow:0 4px 6px rgba(0,0,0,0.1); min-width:120px; z-index:100; }
.dropdown-menu .menu-item { display:block; padding:8px 12px; text-decoration:none; color:#333; }
.dropdown-menu .menu-item:hover { background:#f1f1f1; }
.dropdown-actions.open .dropdown-menu { display:block; }
</style>
</head>
<body>
<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container">
<h1>Journal Management Module</h1>

<table id="journals-table" class="table table-bordered table-striped">
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
<div class="dropdown-actions">
<button type="button" class="btn-more">⋮</button>
<div class="dropdown-menu">
<!-- Edit via Modal -->
<a href="#" class="menu-item" onclick="openEditModal(<?= $row['entry_id'] ?>,'<?= $row['module'] ?>','<?= htmlspecialchars(addslashes($row['reference'])) ?>','<?= htmlspecialchars(addslashes($row['description'])) ?>','<?= $row['status'] ?>')">Edit</a>

<!-- Delete -->
<form method="post" style="display:inline;">
<input type="hidden" name="delete_entry" value="1">
<input type="hidden" name="entry_id" value="<?= $row['entry_id'] ?>">
<button type="submit" class="menu-item" onclick="return confirm('Delete this entry?');">Delete</button>
</form>
</div>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- Add Entry Modal -->
<div id="addEntryModal" class="modal">
<div class="modal-content">
<div class="modal-header">
<h2>Add Journal Entry</h2>
<button class="close-btn" id="closeModal">&times;</button>
</div>
<form method="post">
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
<label>Reference</label>
<select name="reference">
<option value="">-- Manual Entry --</option>
<?php foreach($receipts as $r): ?>
<option value="<?= htmlspecialchars($r['transaction_id']) ?>">TXN: <?= $r['transaction_id'] ?> | ₱<?= number_format($r['grand_total'],2) ?> | <?= $r['status'] ?></option>
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

<!-- Edit Entry Modal -->
<div id="editEntryModal" class="modal">
<div class="modal-content">
<div class="modal-header">
<h2>Edit Journal Entry</h2>
<button class="close-btn" id="closeEditModal">&times;</button>
</div>
<form method="post">
<input type="hidden" name="edit_entry" value="1">
<input type="hidden" name="entry_id" id="edit_entry_id">
<div class="form-group">
<label>Module</label>
<select name="module" id="edit_module" required>
<option value="billing">Patient Billing</option>
<option value="insurance">Insurance</option>
<option value="supply">Supply</option>
<option value="general">General</option>
</select>
</div>
<div class="form-group">
<label>Reference</label>
<select name="reference" id="edit_reference">
<option value="">-- Manual Entry --</option>
<?php foreach($receipts as $r): ?>
<option value="<?= htmlspecialchars($r['transaction_id']) ?>">TXN: <?= $r['transaction_id'] ?> | ₱<?= number_format($r['grand_total'],2) ?> | <?= $r['status'] ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>Description</label>
<textarea name="description" id="edit_description" rows="3"></textarea>
</div>
<div class="form-group">
<label>Status</label>
<select name="status" id="edit_status">
<option value="Draft">Draft</option>
<option value="Posted">Posted</option>
</select>
</div>
<button type="submit">Save Changes</button>
</form>
</div>
</div>

<script>
const addModal = document.getElementById('addEntryModal');
const openModalBtn = document.getElementById('openModal');
const closeModalBtn = document.getElementById('closeModal');
openModalBtn?.addEventListener('click', ()=> addModal.style.display='flex');
closeModalBtn?.addEventListener('click', ()=> addModal.style.display='none');
window.addEventListener('click', e=>{if(e.target==addModal) addModal.style.display='none';});

// Edit Modal
const editModal = document.getElementById('editEntryModal');
const closeEditModalBtn = document.getElementById('closeEditModal');
closeEditModalBtn?.addEventListener('click', ()=> editModal.style.display='none');
window.addEventListener('click', e=>{if(e.target==editModal) editModal.style.display='none';});

function openEditModal(id,module,reference,description,status){
    document.getElementById('edit_entry_id').value=id;
    document.getElementById('edit_module').value=module;
    document.getElementById('edit_reference').value=reference;
    document.getElementById('edit_description').value=description;
    document.getElementById('edit_status').value=status;
    editModal.style.display='flex';
}

// Dropdown actions
document.querySelectorAll('.btn-more').forEach(btn=>{
    btn.addEventListener('click', function(e){
        e.stopPropagation();
        this.parentElement.classList.toggle('open');
    });
});
window.addEventListener('click', ()=>{document.querySelectorAll('.dropdown-actions').forEach(dd=>dd.classList.remove('open'));});

// Expose entries data to JS and auto-open modal if ?open_edit=ID is present
const entriesData = <?= json_encode($__entries_map, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const openId = params.get('open_edit');
    if (openId && entriesData[openId]) {
        const d = entriesData[openId];
        // call existing function to populate and show modal
        openEditModal(openId, d.module ?? '', d.reference ?? '', d.description ?? '', d.status ?? 'Draft');

        // remove open_edit from URL without reloading
        params.delete('open_edit');
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        history.replaceState(null, '', newUrl);
    }
});
</script>

</body>
</html>
