<?php
session_start();
include '../../SQL/config.php';

// Ensure only admin can access
// if (!isset($_SESSION['user_id'])) die("Login required.");
// $user_id = $_SESSION['user_id'];

// $stmt = $pdo->prepare("SELECT role,fname,lname FROM users WHERE user_id=?");
// $stmt->execute([$user_id]);
// $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// if ($currentUser['role'] != 1) { // Example: role=1 is Admin
//     die("You are not authorized to approve budgets.");
// }

// Handle approval form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['budget_id'])) {
    $budget_id = $_POST['budget_id'];
    $status = $_POST['status'];
    $allocated_budget = ($status === 'Approved') ? floatval($_POST['allocated_budget']) : 0;

    $update = $pdo->prepare("
        UPDATE department_budgets
        SET allocated_budget = ?, 
            approved_amount = ?, 
            status = ?
        WHERE budget_id = ?
    ");
    $update->execute([$allocated_budget, $allocated_budget, $status, $budget_id]);

    $msg = "Budget request updated successfully.";
}

// Fetch pending requests
$stmt = $pdo->prepare("
    SELECT b.*, u.department 
    FROM department_budgets b
    JOIN users u ON u.user_id = b.user_id
    WHERE b.status = 'Pending'
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Budget Approval</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="assets/css/Inventory_dashboard.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <h2 class="mb-4">Pending Budget Requests</h2>

    <?php if(isset($msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <table class="table table-bordered bg-white">
        <thead class="table-light">
            <tr>
                <th>Department</th>
                <th>Requested Budget</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($requests) > 0): ?>
            <?php foreach($requests as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['department']) ?></td>
                    <td>₱<?= number_format($r['requested_amount'], 2) ?></td>
                    <td>
                        <button 
                            class="btn btn-sm btn-primary" 
                            data-bs-toggle="modal" 
                            data-bs-target="#approvalModal"
                            data-id="<?= $r['budget_id'] ?>"
                            data-dept="<?= htmlspecialchars($r['department']) ?>"
                            data-requested="<?= $r['requested_amount'] ?>"
                        >
                            View
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="3" class="text-center">No pending requests</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Budget Approval</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="budget_id" id="budget_id">

        <div class="mb-3">
            <label class="form-label">Department</label>
            <input type="text" id="department" class="form-control" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">Requested Budget</label>
            <input type="text" id="requested_budget" class="form-control" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">Allocated Budget</label>
            <input type="number" step="0.01" name="allocated_budget" id="allocated_budget" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="status" class="form-select" required>
                <option value="Approved">Approve</option>
                <option value="Rejected">Decline</option>
            </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const approvalModal = document.getElementById('approvalModal');
approvalModal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    const id = button.getAttribute('data-id');
    const dept = button.getAttribute('data-dept');
    const requested = button.getAttribute('data-requested');

    document.getElementById('budget_id').value = id;
    document.getElementById('department').value = dept;
    document.getElementById('requested_budget').value = "₱" + parseFloat(requested).toLocaleString();
    document.getElementById('allocated_budget').value = requested; // default = requested

    // Reset dropdown
    document.getElementById('status').value = "Approved";
    document.getElementById('allocated_budget').disabled = false;
});

// Disable Allocated Budget input if Declined
document.getElementById('status').addEventListener('change', function() {
    if (this.value === "Rejected") {
        document.getElementById('allocated_budget').disabled = true;
        document.getElementById('allocated_budget').value = 0;
    } else {
        document.getElementById('allocated_budget').disabled = false;
    }
});
</script>

</body>
</html>
