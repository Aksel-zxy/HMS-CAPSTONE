<?php
session_start();
include '../../SQL/config.php';

/* ================================
   FETCH PAYMONGO PAYMENTS AS JOURNAL
================================ */
$result = $conn->query("
    SELECT pp.*,
           pi.fname, pi.mname, pi.lname
    FROM paymongo_payments pp
    LEFT JOIN patientinfo pi ON pp.patient_id = pi.patient_id
    ORDER BY pp.paid_at DESC
");
$payments = $result->fetch_all(MYSQLI_ASSOC);

/* ================================
   FETCH RECEIPTS FOR MANUAL ENTRY
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

/* ================================
   FETCH EXISTING JOURNAL ENTRIES
================================ */
$stmt = $conn->prepare("SELECT * FROM journal_entries ORDER BY entry_date DESC");
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
    border-radius: 20px; 
    padding: 30px; 
    margin-top: 50px; 
}
.table thead { background-color: #007bff; color: white; }
.debit { color: green; font-weight: bold; }
.credit { color: red; font-weight: bold; }
.btn-view { background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; }
.reference-info { white-space: pre-line; font-size: 0.85em; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,.6); justify-content: center; align-items: center; }
.modal-content { background: #fff; padding: 20px; width: 600px; border-radius: 8px; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; }
</style>
</head>
<body class="p-4">


<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container container-wrapper">
<h2>Journal Entries - PayMongo Payments</h2>

<table class="table table-bordered table-striped mt-3">
<thead>
<tr>
<th>Date</th>
<th>Debit Account</th>
<th>Credit Account</th>
<th>Amount</th>
<th>Description</th>
<th>Reference</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($payments as $p):
    $full_name = trim($p['fname'].' '.$p['mname'].' '.$p['lname']);
    $description = "Payment received from {$full_name}\nMethod: {$p['payment_method']}\nRemarks: ".($p['remarks'] ?? '');
    $amount = number_format($p['amount'], 2);
    $date = $p['paid_at'] ?? 'N/A';
?>
<tr>
<td><?= $date ?></td>
<td class="debit">Cash / Bank</td>
<td class="credit">Patient Receivable</td>
<td>₱<?= $amount ?></td>
<td class="reference-info"><?= nl2br(htmlspecialchars($description)) ?></td>
<td><?= htmlspecialchars($p['payment_id']) ?></td>
<td>
<a href="journal_entry_line.php?payment_id=<?= urlencode($p['payment_id']) ?>" class="btn-view">View</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<button id="openModal" class="btn btn-primary mt-3">Add Manual Journal Entry</button>

</div>

<!-- MANUAL ENTRY MODAL -->
<div id="addEntryModal" class="modal">
<div class="modal-content">

<div class="d-flex justify-content-between mb-3">
<h4>Add Manual Journal Entry</h4>
<button class="close-btn" id="closeModal">&times;</button>
</div>

<form method="POST" action="manual_journal_insert.php">

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
