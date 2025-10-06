<?php
session_start();
include '../../SQL/config.php';

// -----------------------------
// ✅ Handle Delete Request
// -----------------------------
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM journal_entries WHERE entry_id = $delete_id");
    header("Location: journal_management.php?msg=deleted");
    exit;
}

// -----------------------------
// ✅ Handle Export Request
// -----------------------------
if (isset($_GET['export_id'])) {
    $export_id = intval($_GET['export_id']);
    $entry = $conn->query("SELECT * FROM journal_entries WHERE entry_id = $export_id")->fetch_assoc();

    if ($entry) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="journal_entry_' . $export_id . '.csv"');
        $output = fopen("php://output", "w");
        fputcsv($output, array_keys($entry));
        fputcsv($output, $entry);
        fclose($output);
        exit;
    }
}

// -----------------------------
// ✅ Handle Edit Submission
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_entry'])) {
    $entry_id = intval($_POST['entry_id']);
    $description = $_POST['description'];
    $reference_type = $_POST['reference_type'] ?? 'Expense'; // default fallback

    $stmt = $conn->prepare("UPDATE journal_entries SET description=?, reference_type=? WHERE entry_id=?");
    $stmt->bind_param("ssi", $description, $reference_type, $entry_id);
    $stmt->execute();
    header("Location: journal_management.php?msg=updated");
    exit;
}

// -----------------------------
// ✅ Fetch Filters & Data
// -----------------------------
$date_from = $_GET['from'] ?? null;
$date_to = $_GET['to'] ?? null;

$sql = "SELECT * FROM journal_entries WHERE 1=1";
$params = [];
$types = "";

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

$total_entries = count($entries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Journal Management Module</title>
  <link rel="stylesheet" href="assets/CSS/journalentry.css">
</head>
<body>

<div class="main-sidebar">
  <?php include 'billing_sidebar.php'; ?>
</div>

<div class="container">
  <header>
    <h1>Journal Management Module</h1>
    <form method="get" class="module-controls" style="display:flex;gap:20px;">
      <div>
        <label>From:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>">
        <label>To:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>">
        <button type="submit" class="btn-filter">Apply Filter</button>
      </div>
    </form>
  </header>

  <table>
    <thead>
      <tr>
        <th>Entry ID</th>
        <th>Date</th>
        <th>Description</th>
        <th>Reference Type</th>
        <th>Reference ID</th>
        <th>Created At</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($entries): ?>
        <?php foreach ($entries as $row): ?>
        <tr>
          <td><?= $row['entry_id'] ?></td>
          <td><?= htmlspecialchars($row['entry_date']) ?></td>
          <td><?= htmlspecialchars($row['description']) ?></td>
          <td><?= htmlspecialchars($row['reference_type']) ?></td>
          <td><?= htmlspecialchars($row['reference_id']) ?></td>
          <td><?= htmlspecialchars($row['created_at']) ?></td>
          <td>
            <a href="journal_entry_line.php?entry_id=<?= $row['entry_id'] ?>" class="btn-view">View Lines</a>
            <div class="dropdown-actions">
              <button class="btn-more">⋮</button>
              <div class="dropdown-menu">
                <button class="menu-item" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                <a href="?delete_id=<?= $row['entry_id'] ?>" class="menu-item" onclick="return confirm('Delete this entry?');">Delete</a>
                <a href="?export_id=<?= $row['entry_id'] ?>" class="menu-item">Export</a>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="7" class="text-center">No journal entries found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="summary">
    <div class="summary-item"><strong>Total Entries:</strong> <?= $total_entries ?></div>
  </div>
</div>

<!-- ✅ Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <h3>Edit Journal Entry</h3>
    <form method="post">
      <input type="hidden" name="edit_entry" value="1">
      <input type="hidden" id="edit_entry_id" name="entry_id">
      <div class="form-group">
        <label>Description:</label>
        <textarea id="edit_description" name="description" rows="3"></textarea>
      </div>
      <div class="form-group">
        <label>Reference Type:</label>
        <select id="edit_reference_type" name="reference_type">
          <option value="Patient Billing">Patient Billing</option>
          <option value="Insurance">Insurance</option>
          <option value="Supply">Supply</option>
          <option value="Expense">Expense</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn-save">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
  // ✅ Dropdown Actions
  document.querySelectorAll('.btn-more').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      document.querySelectorAll('.dropdown-actions').forEach(dd => dd.classList.remove('open'));
      this.parentElement.classList.toggle('open');
    });
  });
  window.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-actions').forEach(dd => dd.classList.remove('open'));
  });

  // ✅ Edit Modal
  const modal = document.getElementById('editModal');
  function openEditModal(entry) {
    document.getElementById('edit_entry_id').value = entry.entry_id;
    document.getElementById('edit_description').value = entry.description;
    document.getElementById('edit_reference_type').value = entry.reference_type;
    modal.style.display = 'flex';
  }
  function closeEditModal() { modal.style.display = 'none'; }
  window.addEventListener('click', e => { if (e.target === modal) closeEditModal(); });
</script>

</body>
</html>
