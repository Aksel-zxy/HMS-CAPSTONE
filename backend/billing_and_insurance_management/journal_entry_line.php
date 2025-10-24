<?php
session_start();
include '../../SQL/config.php';

// ✅ Validate entry_id
$entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
if ($entry_id <= 0) {
    header("Location: journal_entry.php");
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
    <link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
    <link rel="stylesheet" href="assets/CSS/journalentryline.css">
    <style>
        .modal { display: none; }
        .action-links { display: none; } /* hide Edit/Delete column */
        .main-sidebar { float: left; width: 250px; }
        .container { margin-left: 260px; padding: 20px; }

        /* ✅ Hide sidebar and buttons when printing */
        @media print {
            .main-sidebar, .actions, .btn, .btn-primary, .btn-secondary, .btn-success {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            .container {
                margin: 0;
                width: 100%;
                padding: 0;
            }
        }

        .entry-info {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .info-item .label { font-weight: bold; }
        .badge.posted { color: green; font-weight: bold; }
        .badge.draft { color: orange; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th.amount-col, td.amount { text-align: right; }
        .total { font-weight: bold; }
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
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">No lines found for this entry.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>TOTAL</th>
                    <th class="amount"><?= number_format($total_debit, 2) ?></th>
                    <th class="amount"><?= number_format($total_credit, 2) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="actions">
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

<script>
document.getElementById('print-entry').addEventListener('click', function() {
    window.print();
});
</script>

</body>
</html>
