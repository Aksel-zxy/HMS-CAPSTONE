<?php
include '../../SQL/config.php';

// Handle status update
if (isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE repair_requests SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_requests.php"); // refresh
    exit;
}

$result = $conn->query("SELECT * FROM repair_requests ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Repair Requests</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      margin: 0; padding: 0;
    }
    header {
      background: #007bff;
      color: white;
      padding: 15px;
      font-size: 20px;
      text-align: center;
    }
    .container {
      padding: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
    }
    table th, table td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: left;
      font-size: 14px;
    }
    table th {
      background: #007bff;
      color: white;
    }
    .status-open { color: red; font-weight: bold; }
    .status-progress { color: orange; font-weight: bold; }
    .status-completed { color: green; font-weight: bold; }
    select, button {
      padding: 5px;
      font-size: 13px;
    }
    .update-btn {
      background: #28a745;
      color: white;
      border: none;
      padding: 6px 10px;
      cursor: pointer;
      border-radius: 4px;
    }
    .update-btn:hover {
      background: #218838;
    }
  </style>
</head>
<body>

<header>🔧 Admin Dashboard - Repair Requests</header>
<div class="container">
  <table>
    <tr>
      <th>Ticket No</th>
      <th>User</th>
      <th>Equipment</th>
      <th>Issue</th>
      <th>Location</th>
      <th>Priority</th>
      <th>Status</th>
      <th>Date/Time</th>
      <th>Action</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?php echo $row['ticket_no']; ?></td>
        <td><?php echo $row['user_name']; ?></td>
        <td><?php echo $row['equipment']; ?></td>
        <td><?php echo $row['issue']; ?></td>
        <td><?php echo $row['location']; ?></td>
        <td><?php echo $row['priority']; ?></td>
        <td>
          <span class="<?php
            if ($row['status'] == 'Open') echo 'status-open';
            elseif ($row['status'] == 'In Progress') echo 'status-progress';
            else echo 'status-completed';
          ?>">
            <?php echo $row['status']; ?>
          </span>
        </td>
        <td><?php echo $row['created_at']; ?></td>
        <td>
          <form method="POST" style="display:flex; gap:5px;">
            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
            <select name="status">
              <option value="Open" <?php if ($row['status']=="Open") echo "selected"; ?>>Open</option>
              <option value="In Progress" <?php if ($row['status']=="In Progress") echo "selected"; ?>>In Progress</option>
              <option value="Completed" <?php if ($row['status']=="Completed") echo "selected"; ?>>Completed</option>
            </select>
            <button type="submit" name="update_status" class="update-btn">Update</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

</body>
</html>
