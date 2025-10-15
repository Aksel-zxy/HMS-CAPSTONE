<?php
session_start();
include '../../SQL/config.php';

// ✅ Validate entry_id
$entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
if ($entry_id <= 0) {
    // Instead of dying, redirect back to journal entries list
    header("Location: journal_entry.php");
    exit;
}

// ✅ Handle Add Line Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_line'])) {
    $account_name = $_POST['account_name'];
    $debit = floatval($_POST['debit']);
    $credit = floatval($_POST['credit']);
    $description = $_POST['description'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isdds", $entry_id, $account_name, $debit, $credit, $description);
    $stmt->execute();

    header("Location: journal_entry_line.php?entry_id=" . $entry_id);
    exit;
}

// ✅ Handle Delete Line
if (isset($_GET['delete_line'])) {
    $line_id = intval($_GET['delete_line']);
    $stmt = $conn->prepare("DELETE FROM journal_entry_lines WHERE line_id = ? AND entry_id = ?");
    $stmt->bind_param("ii", $line_id, $entry_id);
    $stmt->execute();

    header("Location: journal_entry_line.php?entry_id=" . $entry_id);
    exit;
}

// ✅ Fetch entry info
$stmt = $conn->prepare("SELECT * FROM journal_entries WHERE entry_id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
if (!$entry) {
    // If entry is missing, redirect back
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
    <link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
    <link rel="stylesheet" href="assets/CSS/journalentryline.css">
    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #fff;
            padding: 20px;
            width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { margin: 0; }
        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;
        }
        .action-links a {
            margin-right: 10px;
            text-decoration: none;
            color: #007bff;
        }
        .action-links a.delete {
            color: red;
        }
    </style>
</head>
<body>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container">
    <header>
        <h1>Journal Entry Lines - Entry #<?= $entry['entry_id'] ?></h1>
        <div class="entry-info">
            <div class="info-item">
                <span class="label">Date:</span>
                <span class="value"><?= htmlspecialchars($entry['entry_date']) ?></span>
            </div>
            <div class="info-item">
                <span class="label">Status:</span>
                <span class="badge <?= strtolower($entry['status']) ?>"><?= $entry['status'] ?></span>
            </div>
            <div class="info-item">
                <span class="label">Reference:</span>
                <span class="value"><?= htmlspecialchars($entry['reference']) ?></span>
            </div>
        </div>
    </header>

    <div class="table-container">
        <table id="entry-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="amount-col">Debit</th>
                    <th class="amount-col">Credit</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($lines): ?>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?= htmlspecialchars($line['account_name']) ?></td>
                        <td class="amount debit"><?= $line['debit'] > 0 ? number_format($line['debit'], 2) : '' ?></td>
                        <td class="amount credit"><?= $line['credit'] > 0 ? number_format($line['credit'], 2) : '' ?></td>
                        <td><?= htmlspecialchars($line['description']) ?></td>
                        <td class="action-links">
                            <a href="edit_journal_entry_line.php?line_id=<?= $line['line_id'] ?>&entry_id=<?= $entry['entry_id'] ?>">Edit</a>
                            <a href="journal_entry_line.php?entry_id=<?= $entry['entry_id'] ?>&delete_line=<?= $line['line_id'] ?>" class="delete" onclick="return confirm('Delete this line?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center">No lines found for this entry.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>TOTAL</th>
                    <th class="amount total"><?= number_format($total_debit, 2) ?></th>
                    <th class="amount total"><?= number_format($total_credit, 2) ?></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="actions">
        <button id="openModal" class="btn-secondary">+ Add Line</button>
        <a href="edit_journal_entry.php?id=<?= $entry['entry_id'] ?>" class="btn-primary">Edit Entry</a>
        <?php if ($entry['status'] === 'Draft'): ?>
            <a href="post_journal_entry.php?id=<?= $entry['entry_id'] ?>" class="btn-success"
               onclick="return confirm('Post this entry? This action cannot be undone.');">Post Entry</a>
        <?php endif; ?>
        <button id="print-entry" class="btn-secondary">Print</button>
        <a href="journal_entry.php" class="btn-secondary">Back</a>
    </div>

    <div class="entry-details">
        <h2>Entry Details</h2>
        <div class="details-grid">
            <div class="detail-item">
                <span class="label">Created By:</span>
                <span class="value"><?= htmlspecialchars($entry['created_by']) ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Created Date:</span>
                <span class="value"><?= htmlspecialchars($entry['created_at']) ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Last Modified:</span>
                <span class="value"><?= htmlspecialchars($entry['updated_at']) ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Module:</span>
                <span class="value"><?= ucfirst($entry['module']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ✅ Modal for Add Line -->
<div id="addLineModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add Line</h2>
      <button class="close-btn" id="closeModal">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="add_line" value="1">

      <div class="form-group">
        <label for="account_name">Account Name</label>
        <input type="text" id="account_name" name="account_name" required>
      </div>

      <div class="form-group">
        <label for="debit">Debit</label>
        <input type="number" step="0.01" id="debit" name="debit" value="0">
      </div>

      <div class="form-group">
        <label for="credit">Credit</label>
        <input type="number" step="0.01" id="credit" name="credit" value="0">
      </div>

      <div class="form-group">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3"></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-primary">Save Line</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('print-entry').addEventListener('click', function() {
    window.print();
});

const modal = document.getElementById('addLineModal');
const openModalBtn = document.getElementById('openModal');
const closeModalBtn = document.getElementById('closeModal');

openModalBtn.addEventListener('click', () => { modal.style.display = 'flex'; });
closeModalBtn.addEventListener('click', () => { modal.style.display = 'none'; });
window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
</script>
</body>
</html>
