<?php
include '../../SQL/config.php';

// Handle Add Line
if (isset($_POST['add_line'])) {
    $entryId = $_POST['entry_id'];
    $accountId = $_POST['account_id'];
    $debit = $_POST['debit'];
    $credit = $_POST['credit'];

    $sql = "INSERT INTO journal_entry_line (entry_id, account_id, debit, credit) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iidd", $entryId, $accountId, $debit, $credit);
    $stmt->execute();
}

// Fetch lines
$lines = $conn->query("SELECT l.line_id, l.entry_id, a.account_name, l.debit, l.credit 
    FROM journal_entry_lines l
    JOIN journal_account a ON l.account_id = a.account_id");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Journal Entry Lines</title>
</head>
<body>
    <h2>Journal Entry Lines</h2>

    <form method="POST">
        <input type="number" name="entry_id" placeholder="Entry ID" required>
        <input type="number" name="account_id" placeholder="Account ID" required>
        <input type="number" step="0.01" name="debit" placeholder="Debit">
        <input type="number" step="0.01" name="credit" placeholder="Credit">
        <button type="submit" name="add_line">Add Line</button>
    </form>

    <h3>Existing Lines</h3>
    <table border="1">
        <tr>
            <th>ID</th><th>Entry</th><th>Account</th><th>Debit</th><th>Credit</th>
        </tr>
        <?php while($row = $lines->fetch_assoc()): ?>
        <tr>
            <td><?= $row['line_id'] ?></td>
            <td><?= $row['entry_id'] ?></td>
            <td><?= $row['account_name'] ?></td>
            <td><?= $row['debit'] ?></td>
            <td><?= $row['credit'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
