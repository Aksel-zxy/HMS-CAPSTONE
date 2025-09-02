<?php
include '../../SQL/config.php';

// Handle Add Account
if (isset($_POST['add_account'])) {
    $name = $_POST['account_name'];
    $type = $_POST['account_type'];
    $sql = "INSERT INTO journal_account (account_name, account_type) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
}

// Fetch all accounts
$accounts = $conn->query("SELECT * FROM journal_account");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Journal Accounts</title>
</head>
<body>
    <h2>Chart of Accounts</h2>

    <form method="POST">
        <input type="text" name="account_name" placeholder="Account Name" required>
        <select name="account_type" required>
            <option value="Asset">Asset</option>
            <option value="Liability">Liability</option>
            <option value="Equity">Equity</option>
            <option value="Revenue">Revenue</option>
            <option value="Expense">Expense</option>
        </select>
        <button type="submit" name="add_account">Add Account</button>
    </form>

    <h3>Existing Accounts</h3>
    <table border="1">
        <tr>
            <th>ID</th><th>Name</th><th>Type</th><th>Balance</th>
        </tr>
        <?php while($row = $accounts->fetch_assoc()): ?>
        <tr>
            <td><?= $row['account_id'] ?></td>
            <td><?= $row['account_name'] ?></td>
            <td><?= $row['account_type'] ?></td>
            <td><?= $row['balance'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
