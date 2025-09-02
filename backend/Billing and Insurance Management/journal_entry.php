<?php
include '../../SQL/config.php';

// Handle Add Entry
if (isset($_POST['add_entry'])) {
    $date = $_POST['entry_date'];
    $desc = $_POST['description'];
    $refType = $_POST['reference_type'];
    $refId = $_POST['reference_id'];

    $sql = "INSERT INTO journal_entries (entry_date, description, reference_type, reference_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $date, $desc, $refType, $refId);
    $stmt->execute();
}

// Fetch entries
$entries = $conn->query("SELECT * FROM journal_entries ORDER BY entry_date DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Journal Entries</title>
</head>
<body>
    <h2>Journal Entries</h2>

    <form method="POST">
        <input type="date" name="entry_date" required>
        <input type="text" name="description" placeholder="Description" required>
        <select name="reference_type" required>
            <option value="Billing">Billing</option>
            <option value="Insurance">Insurance</option>
            <option value="Supply">Supply</option>
        </select>
        <input type="number" name="reference_id" placeholder="Reference ID">
        <button type="submit" name="add_entry">Add Entry</button>
    </form>

    <h3>Existing Entries</h3>
    <table border="1">
        <tr>
            <th>ID</th><th>Date</th><th>Description</th><th>Type</th><th>Ref ID</th>
        </tr>
        <?php while($row = $entries->fetch_assoc()): ?>
        <tr>
            <td><?= $row['entry_id'] ?></td>
            <td><?= $row['entry_date'] ?></td>
            <td><?= $row['description'] ?></td>
            <td><?= $row['reference_type'] ?></td>
            <td><?= $row['reference_id'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
