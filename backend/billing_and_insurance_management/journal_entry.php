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
    $reference = $_POST['reference'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE journal_entries SET description=?, reference=?, status=? WHERE entry_id=?");
    $stmt->bind_param("sssi", $description, $reference, $status, $entry_id);
    $stmt->execute();
    header("Location: journal_management.php?msg=updated");
    exit;
}

// -----------------------------
// ✅ Fetch Filters & Data
// -----------------------------
$module_filter = $_GET['module'] ?? 'all';
$date_from = $_GET['from'] ?? null;
$date_to = $_GET['to'] ?? null;

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

$total_entries = count($entries);
$total_posted = count(array_filter($entries, fn($e) => $e['status'] === 'Posted'));
$total_draft = $total_entries - $total_posted;
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
        <label>Module:</label>
        <select name="module">
          <option value="all" <?= $module_filter=="all"?"selected":"" ?>>All</option>
          <option value="billing" <?= $module_filter=="billing"?"selected":"" ?>>Patient Billing</option>
          <option value="insurance" <?= $module_filter=="insurance"?"selected":"" ?>>Insurance</option>
          <option value="supply" <?= $module_filter=="supply"?"selected":"" ?>>Supply</option>
          <option value="general" <?= $module_filter=="general"?"selected":"" ?>>General</option>
        </select>
      </div>
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
        <th>Reference</th>
        <th>Module</th>
        <th>Created By</th>
        <th>Status</th>
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
          <td><?= htmlspecialchars($row['reference']) ?></td>
          <td><span class="badge <?= strtolower($row['module']) ?>"><?= ucfirst($row['module']) ?></span></td>
          <td><?= htmlspecialchars($row['created_by']) ?></td>
          <td><span class="status <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
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
        <tr><td colspan="8" class="text-center">No journal entries found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="summary">
    <div class="summary-item"><strong>Total Entries:</strong> <?= $total_entries ?></div>
    <div class="summary-item"><strong>Posted:</strong> <?= $total_posted ?></div>
    <div class="summary-item"><strong>Draft:</strong> <?= $total_draft ?></div>
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
        <label>Reference:</label>
        <input type="text" id="edit_reference" name="reference">
      </div>
      <div class="form-group">
        <label>Status:</label>
        <select id="edit_status" name="status">
          <option value="Draft">Draft</option>
          <option value="Posted">Posted</option>
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
    document.getElementById('edit_reference').value = entry.reference;
    document.getElementById('edit_status').value = entry.status;
    modal.style.display = 'flex';
  }
  function closeEditModal() { modal.style.display = 'none'; }
  window.addEventListener('click', e => { if (e.target === modal) closeEditModal(); });
</script>

</body>
</html>
