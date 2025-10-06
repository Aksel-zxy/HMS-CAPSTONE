<?php
session_start();
include '../../SQL/config.php';

// ✅ Handle Add Journal Entry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $module = $_POST['module'];
    $reference = $_POST['reference'];
    $description = $_POST['description'] ?? null;
    $status = $_POST['status'] ?? 'Draft';
    $created_by = $_SESSION['username'] ?? "Admin"; // use logged-in user if available

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

// Filter by module
if ($module_filter !== "all") {
    $sql .= " AND module = ?";
    $params[] = $module_filter;
    $types .= "s";
}

// Filter by date range
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
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$entries = $result->fetch_all(MYSQLI_ASSOC);

// ✅ Count totals
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
  <style>
    .modal {
      display: none; position: fixed; z-index: 1000;
      left: 0; top: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.6); justify-content: center; align-items: center;
    }
    .modal-content {
      background: #fff; padding: 20px; width: 500px;
      border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; }
    .modal-header h2 { margin: 0; }
    .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;
    }
    .status.posted { color: green; font-weight: bold; }
    .status.draft { color: orange; font-weight: bold; }
    .badge.billing { background: #007bff; color: #fff; padding: 2px 6px; border-radius: 4px; }
    .badge.insurance { background: #28a745; color: #fff; padding: 2px 6px; border-radius: 4px; }
    .badge.supply { background: #6c757d; color: #fff; padding: 2px 6px; border-radius: 4px; }
    .badge.general { background: #17a2b8; color: #fff; padding: 2px 6px; border-radius: 4px; }
    .btn-view, .btn-more { margin-right: 5px; }
    .dropdown-actions { position: relative; display: inline-block; }
    .dropdown-menu {
      display: none; position: absolute; right: 0;
      background: #fff; border: 1px solid #ccc;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      min-width: 120px; z-index: 100;
    }
    .dropdown-menu .menu-item {
      display: block; padding: 8px 12px; text-decoration: none; color: #333;
    }
    .dropdown-menu .menu-item:hover { background: #f1f1f1; }
    .dropdown-actions.open .dropdown-menu { display: block; }
  </style>
</head>
<body>

<div class="main-sidebar">
  <?php include 'billing_sidebar.php'; ?>
</div>

<div class="container">
  <header>
    <h1>Journal Management Module</h1>
    <form method="get" class="module-controls">
      <div class="dropdown-container">
        <label for="module-dropdown">Module:</label>
        <select id="module-dropdown" name="module">
          <option value="all" <?= $module_filter=="all"?"selected":"" ?>>All Modules</option>
          <option value="billing" <?= $module_filter=="billing"?"selected":"" ?>>Patient Billing</option>
          <option value="insurance" <?= $module_filter=="insurance"?"selected":"" ?>>Insurance</option>
          <option value="supply" <?= $module_filter=="supply"?"selected":"" ?>>Supply</option>
          <option value="general" <?= $module_filter=="general"?"selected":"" ?>>General</option>
        </select>
      </div>
      <div class="date-filter">
        <label for="date-from">From:</label>
        <input type="date" id="date-from" name="from" value="<?= htmlspecialchars($date_from) ?>">
        <label for="date-to">To:</label>
        <input type="date" id="date-to" name="to" value="<?= htmlspecialchars($date_to) ?>">
        <button type="submit" class="btn-filter">Apply Filter</button>
      </div>
    </form>
  </header>

  <div class="table-container">
    <div class="table-header">
      <h2>Journal Entries</h2>
      <div class="entries-count">Showing <?= $total_entries ?> entries</div>
    </div>
    <table id="journals-table">
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
                <button type="button" class="btn-more">⋮</button>
                <div class="dropdown-menu">
                  <a href="edit_journal_entry.php?id=<?= $row['entry_id'] ?>" class="menu-item">Edit</a>
                  <a href="delete_journal_entry.php?id=<?= $row['entry_id'] ?>" class="menu-item" onclick="return confirm('Delete this entry?');">Delete</a>
                  <a href="export_journal_entry.php?id=<?= $row['entry_id'] ?>" class="menu-item">Export</a>
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
  </div>

  <div class="actions">
    <button id="openModal" class="btn-primary">
      <span class="icon">+</span> Add Journal Entry
    </button>
  </div>

  <div class="summary">
    <div class="summary-item"><span class="label">Total Entries:</span> <span class="value"><?= $total_entries ?></span></div>
    <div class="summary-item"><span class="label">Posted:</span> <span class="value"><?= $total_posted ?></span></div>
    <div class="summary-item"><span class="label">Draft:</span> <span class="value"><?= $total_draft ?></span></div>
  </div>
</div>

<!-- ✅ Modal Form -->
<div id="addEntryModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add Journal Entry</h2>
      <button class="close-btn" id="closeModal">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="add_entry" value="1">
      
      <div class="form-group">
        <label for="module">Module</label>
        <select name="module" id="module" required>
          <option value="billing">Patient Billing</option>
          <option value="insurance">Insurance</option>
        </select>
      </div>

      <div class="form-group">
        <label for="reference">Reference (from Patient Receipt)</label>
        <select id="reference" name="reference">
          <option value="">-- Manual Entry (no receipt) --</option>
          <?php foreach ($receipts as $r): ?>
            <option value="<?= htmlspecialchars($r['transaction_id']) ?>">
              <?= "TXN: {$r['transaction_id']} | {$r['payment_method']} | ₱" . number_format($r['grand_total'], 2) . " | {$r['status']}" ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3"></textarea>
      </div>

      <div class="form-group">
        <label for="status">Status</label>
        <select name="status" id="status">
          <option value="Draft">Draft</option>
          <option value="Posted">Posted</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-primary">Save Entry</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Modal
  const modal = document.getElementById('addEntryModal');
  const openModalBtn = document.getElementById('openModal');
  const closeModalBtn = document.getElementById('closeModal');
  openModalBtn.addEventListener('click', () => { modal.style.display = 'flex'; });
  closeModalBtn.addEventListener('click', () => { modal.style.display = 'none'; });
  window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

  // Dropdown actions
  document.querySelectorAll('.btn-more').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      this.parentElement.classList.toggle('open');
    });
  });
  window.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-actions').forEach(dd => dd.classList.remove('open'));
  });
</script>

</body>
</html>
