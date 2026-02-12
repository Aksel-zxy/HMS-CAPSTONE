<?php
session_start();
include '../../SQL/config.php';

/* ===============================
   ADD JOURNAL ENTRY
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {

    $module      = $_POST['module'];
    $receipt_id  = !empty($_POST['reference']) ? $_POST['reference'] : null;
    $description = '';
    $status      = $_POST['status'] ?? 'Draft';
    $created_by  = $_SESSION['username'] ?? 'Admin';

    $reference_text = "Manual Journal Entry";

    /* ===============================
       IF RECEIPT SELECTED
       - Build Reference Text
       - Build Description from billing_items
    ================================ */
    if (!empty($receipt_id)) {

        // Get receipt + patient info
        $stmt = $conn->prepare("
            SELECT pr.transaction_id, pr.payment_method, pr.grand_total, pr.status,
                   pr.patient_id,
                   pi.fname, pi.mname, pi.lname
            FROM patient_receipt pr
            LEFT JOIN patientinfo pi ON pr.patient_id = pi.patient_id
            WHERE pr.receipt_id = ?
        ");
        $stmt->bind_param("i", $receipt_id);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($patient) {

            $full_name = trim($patient['fname'].' '.$patient['mname'].' '.$patient['lname']);
            $reference_text = "Receipt #{$receipt_id} | {$full_name} | TXN {$patient['transaction_id']} | Method: {$patient['payment_method']} | ₱".number_format($patient['grand_total'],2)." | {$patient['status']}";

            // Fetch billing items for this receipt/patient
            $stmt = $conn->prepare("
                SELECT bi.item_id, s.service_name, bi.quantity, bi.unit_price, bi.total_price
                FROM billing_items bi
                LEFT JOIN services s ON bi.service_id = s.service_id
                WHERE bi.billing_id = ?
            ");
            $stmt->bind_param("i", $patient['patient_id']); // Use patient_id or billing_id based on your schema
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if ($items) {
                $description .= "Billing Items:\n";
                foreach ($items as $item) {
                    $description .= "- {$item['service_name']} | Qty: {$item['quantity']} | Unit: ₱".number_format($item['unit_price'],2)." | Total: ₱".number_format($item['total_price'],2)."\n";
                }
            }
        }
    }

    /* ===============================
       INSERT ENTRY
    ================================ */
    $stmt = $conn->prepare("
        INSERT INTO journal_entries 
        (entry_date, module, description, reference, status, created_by)
        VALUES (NOW(), ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss",
        $module,
        $description,
        $reference_text,
        $status,
        $created_by
    );
    $stmt->execute();
    $new_entry_id = $stmt->insert_id;
    $stmt->close();

    header("Location: journal_entry_line.php?entry_id=".$new_entry_id);
    exit;
}

/* ===============================
   FETCH RECEIPTS WITH PATIENT NAME
================================ */
$receipts = $conn->query("
    SELECT 
        pr.receipt_id,
        pr.transaction_id,
        pr.payment_method,
        pr.grand_total,
        pr.status,
        pr.patient_id,
        pi.fname, pi.mname, pi.lname
    FROM patient_receipt pr
    LEFT JOIN patientinfo pi ON pr.patient_id = pi.patient_id
    ORDER BY pr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

/* ===============================
   FETCH JOURNAL ENTRIES
================================ */
$stmt = $conn->prepare("
    SELECT * FROM journal_entries 
    ORDER BY entry_date DESC
");
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Management Module</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.container-wrapper { 
    background-color: white; 
    border-radius: 30px; 
    padding: 30px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    margin-top: 80px; 
}
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,.6); justify-content: center; align-items: center; }
.modal-content { background: #fff; padding: 20px; width: 600px; border-radius: 8px; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; }
.status.posted { color: green; font-weight: bold; }
.status.draft { color: orange; font-weight: bold; }
.badge.billing { background-color: #007bff; }
.badge.insurance { background-color: #28a745; }
.badge.supply { background-color: #6c757d; }
.badge.general { background-color: #17a2b8; }
.btn-view { background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; }
.reference-info { font-size: 0.85em; color: #555; white-space: pre-line; }
.module-info { font-size: 0.85em; color: #333; display: block; margin-top: 3px; }
</style>
</head>

<body class="p-4">

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container">
<div class="container-wrapper">

<h1 class="mb-4">Journal Management Module</h1>

<table class="table table-bordered table-striped">
<thead>
<tr>
<th>ID</th>
<th>Date</th>
<th>Description</th>
<th>Reference</th>
<th>Module</th>
<th>Created By</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($entries as $row): ?>
<tr>
<td><?= $row['entry_id'] ?></td>
<td><?= $row['entry_date'] ?></td>
<td class="reference-info"><?= nl2br(htmlspecialchars($row['description'])) ?></td>
<td class="reference-info"><?= htmlspecialchars($row['reference']) ?></td>
<td>
<span class="badge <?= strtolower($row['module']) ?>"><?= ucfirst($row['module']) ?></span>
<?php if(str_contains(strtolower($row['reference']), 'paid')): ?>
    <span class="module-info">Payment Completed</span>
<?php elseif(str_contains(strtolower($row['reference']), 'pending')): ?>
    <span class="module-info">Payment Pending</span>
<?php endif; ?>
</td>
<td><?= htmlspecialchars($row['created_by']) ?></td>
<td><span class="status <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
<td>
<a href="journal_entry_line.php?entry_id=<?= $row['entry_id'] ?>" class="btn-view">View</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<button id="openModal" class="btn btn-primary mt-3">Add Journal Entry</button>

</div>
</div>

<!-- MODAL -->
<div id="addEntryModal" class="modal">
<div class="modal-content">

<div class="d-flex justify-content-between mb-3">
<h4>Add Journal Entry</h4>
<button class="close-btn" id="closeModal">&times;</button>
</div>

<form method="POST">

<input type="hidden" name="add_entry" value="1">

<div class="mb-3">
<label>Module</label>
<select name="module" class="form-control" required>
<option value="billing">Patient Billing</option>
<option value="insurance">Insurance</option>
<option value="supply">Supply</option>
<option value="general">General</option>
</select>
</div>

<div class="mb-3">
<label>Reference (Receipt)</label>
<select name="reference" class="form-control">
<option value="">-- Manual Entry --</option>
<?php foreach ($receipts as $r): 
    $full_name = trim($r['fname'].' '.$r['mname'].' '.$r['lname']);
?>
<option value="<?= $r['receipt_id'] ?>">
Receipt #<?= $r['receipt_id'] ?> | <?= $full_name ?> | TXN <?= $r['transaction_id'] ?> | <?= $r['payment_method'] ?> | ₱<?= number_format($r['grand_total'],2) ?> | <?= $r['status'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Description</label>
<textarea name="description" class="form-control" rows="4" placeholder="Optional: Auto-filled when receipt selected"></textarea>
</div>

<div class="mb-3">
<label>Status</label>
<select name="status" class="form-control">
<option value="Draft">Draft</option>
<option value="Posted">Posted</option>
</select>
</div>

<button type="submit" class="btn btn-success">Save Entry</button>

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
