<?php
session_start();
include '../../SQL/config.php';

// ✅ Validate entry_id
$entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
if ($entry_id <= 0) {
    header("Location: journal_entry.php");
    exit;
}

// ✅ Handle Add Line
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_line'])) {
    $account_name = $_POST['account_name'];
    $debit = floatval($_POST['debit']);
    $credit = floatval($_POST['credit']);
    $description = $_POST['description'] ?? null;

    $stmt = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdds", $entry_id, $account_name, $debit, $credit, $description);
    $stmt->execute();
    header("Location: journal_entry_line.php?entry_id=" . $entry_id);
    exit;
}

// ✅ Handle Edit Line
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_line'])) {
    $line_id = intval($_POST['line_id']);
    $account_name = $_POST['account_name'];
    $debit = floatval($_POST['debit']);
    $credit = floatval($_POST['credit']);
    $description = $_POST['description'];

    $stmt = $conn->prepare("UPDATE journal_entry_lines SET account_name=?, debit=?, credit=?, description=? WHERE line_id=? AND entry_id=?");
    $stmt->bind_param("sddssi", $account_name, $debit, $credit, $description, $line_id, $entry_id);
    $stmt->execute();
    header("Location: journal_entry_line.php?entry_id=" . $entry_id);
    exit;
}

// ✅ Handle Delete Line
if (isset($_GET['delete_line'])) {
    $line_id = intval($_GET['delete_line']);
    $stmt = $conn->prepare("DELETE FROM journal_entry_lines WHERE line_id=? AND entry_id=?");
    $stmt->bind_param("ii", $line_id, $entry_id);
    $stmt->execute();
    header("Location: journal_entry_line.php?entry_id=" . $entry_id);
    exit;
}

// ✅ Handle Export Entry + Lines
if (isset($_GET['export'])) {
    $entry = $conn->query("SELECT * FROM journal_entries WHERE entry_id = $entry_id")->fetch_assoc();
    $lines = $conn->query("SELECT * FROM journal_entry_lines WHERE entry_id = $entry_id")->fetch_all(MYSQLI_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="journal_entry_' . $entry_id . '.csv"');
    $out = fopen("php://output", "w");

    fputcsv($out, ["Journal Entry ID", "Date", "Reference Type", "Reference ID", "Description", "Created At"]);
    fputcsv($out, [$entry['entry_id'], $entry['entry_date'], $entry['reference_type'], $entry['reference_id'], $entry['description'], $entry['created_at']]);
    fputcsv($out, []); // blank line
    fputcsv($out, ["Line ID", "Account Name", "Debit", "Credit", "Description"]);

    foreach ($lines as $line) {
        fputcsv($out, [$line['line_id'], $line['account_name'], $line['debit'], $line['credit'], $line['description']]);
    }

    fclose($out);
    exit;
}

// ✅ Fetch entry info
$stmt = $conn->prepare("SELECT * FROM journal_entries WHERE entry_id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
if (!$entry) {
    header("Location: journal_entry.php");
    exit;
}

// ✅ Fetch entry lines
$stmt = $conn->prepare("SELECT * FROM journal_entry_lines WHERE entry_id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ Totals
$total_debit = array_sum(array_column($lines, 'debit'));
$total_credit = array_sum(array_column($lines, 'credit'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Entry Lines - Entry #<?= $entry['entry_id'] ?></title>
<link rel="stylesheet" href="assets/CSS/journalentryline.css">
<style>
/* Basic styling for table and modals */
.container { margin-left: 250px; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { padding: 10px; border-bottom: 1px solid #ccc; }
th { background: #f8f9fa; }
.amount { text-align: right; }
.amount.total { font-weight: bold; }
.action-links a { margin-right: 8px; text-decoration: none; color: #007bff; }
.action-links a.delete { color: red; }
.modal {
  display: none; position: fixed; z-index: 1000;
  left: 0; top: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.5); justify-content: center; align-items: center;
}
.modal-content {
  background: #fff; padding: 20px; width: 400px;
  border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
.close-btn { background: none; border: none; font-size: 20px; cursor: pointer; }
.btn-primary, .btn-secondary, .btn-success {
  padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; color: #fff;
}
.btn-primary { background: #007bff; }
.btn-secondary { background: #6c757d; }
.btn-success { background: #28a745; }
.btn-primary:hover { background: #0056b3; }
.btn-success:hover { background: #218838; }
.btn-secondary:hover { background: #5a6268; }
</style>
</head>
<body>

<div class="main-sidebar">
  <?php include 'billing_sidebar.php'; ?>
</div>

<div class="container">
  <h1>Journal Entry Lines - Entry #<?= $entry['entry_id'] ?></h1>
  <p><strong>Date:</strong> <?= htmlspecialchars($entry['entry_date']) ?> |
     <strong>Reference:</strong> <?= htmlspecialchars($entry['reference_type']) ?> #<?= htmlspecialchars($entry['reference_id']) ?></p>
  <p><strong>Description:</strong> <?= htmlspecialchars($entry['description']) ?></p>
  <p><strong>Created At:</strong> <?= htmlspecialchars($entry['created_at']) ?></p>

  <div class="actions" style="margin:15px 0;">
    <button id="openAddModal" class="btn-primary">+ Add Line</button>
    <button class="btn-secondary" onclick="window.location='?entry_id=<?= $entry_id ?>&export=1'">Export CSV</button>
    <button id="printEntry" class="btn-secondary">Print</button>
    <a href="journal_entry.php" class="btn-secondary">Back</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>Account</th>
        <th>Debit</th>
        <th>Credit</th>
        <th>Description</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($lines): ?>
        <?php foreach ($lines as $line): ?>
        <tr>
          <td><?= htmlspecialchars($line['account_name']) ?></td>
          <td class="amount"><?= $line['debit'] > 0 ? number_format($line['debit'], 2) : '' ?></td>
          <td class="amount"><?= $line['credit'] > 0 ? number_format($line['credit'], 2) : '' ?></td>
          <td><?= htmlspecialchars($line['description']) ?></td>
          <td class="action-links">
            <a href="#" onclick='openEditModal(<?= json_encode($line) ?>)'>Edit</a>
            <a href="?entry_id=<?= $entry_id ?>&delete_line=<?= $line['line_id'] ?>" class="delete" onclick="return confirm('Delete this line?');">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="5" class="text-center">No lines found.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <th>Total</th>
        <th class="amount total"><?= number_format($total_debit, 2) ?></th>
        <th class="amount total"><?= number_format($total_credit, 2) ?></th>
        <th colspan="2"></th>
      </tr>
    </tfoot>
  </table>
</div>

<!-- Add Line Modal -->
<div id="addLineModal" class="modal">
  <div class="modal-content">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <h3>Add Line</h3><button class="close-btn" onclick="closeAddModal()">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="add_line" value="1">
      <label>Account Name</label>
      <input type="text" name="account_name" required>
      <label>Debit</label>
      <input type="number" step="0.01" name="debit">
      <label>Credit</label>
      <input type="number" step="0.01" name="credit">
      <label>Description</label>
      <textarea name="description"></textarea>
      <div style="text-align:right;margin-top:10px;">
        <button type="submit" class="btn-success">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Line Modal -->
<div id="editLineModal" class="modal">
  <div class="modal-content">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <h3>Edit Line</h3><button class="close-btn" onclick="closeEditModal()">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="edit_line" value="1">
      <input type="hidden" name="line_id" id="edit_line_id">
      <label>Account Name</label>
      <input type="text" name="account_name" id="edit_account_name" required>
      <label>Debit</label>
      <input type="number" step="0.01" name="debit" id="edit_debit">
      <label>Credit</label>
      <input type="number" step="0.01" name="credit" id="edit_credit">
      <label>Description</label>
      <textarea name="description" id="edit_description"></textarea>
      <div style="text-align:right;margin-top:10px;">
        <button type="submit" class="btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
// ✅ Add Modal
const addModal = document.getElementById('addLineModal');
function closeAddModal(){ addModal.style.display='none'; }
document.getElementById('openAddModal').onclick = () => addModal.style.display='flex';

// ✅ Edit Modal
const editModal = document.getElementById('editLineModal');
function openEditModal(line) {
  document.getElementById('edit_line_id').value = line.line_id;
  document.getElementById('edit_account_name').value = line.account_name;
  document.getElementById('edit_debit').value = line.debit;
  document.getElementById('edit_credit').value = line.credit;
  document.getElementById('edit_description').value = line.description;
  editModal.style.display = 'flex';
}
function closeEditModal(){ editModal.style.display='none'; }

// ✅ Modal click outside to close
window.addEventListener('click', function(e){
  if(e.target === addModal) closeAddModal();
  if(e.target === editModal) closeEditModal();
});

// ✅ Print
document.getElementById('printEntry').addEventListener('click', ()=>window.print());
</script>

</body>
</html>
