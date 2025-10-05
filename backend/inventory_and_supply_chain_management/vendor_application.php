<?php
require 'db.php';
$vendors = $pdo->query("SELECT id, registration_number, company_name, status, approved_at FROM vendors ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendor Applications</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<body class="p-4">
<div class="container">
    <h2 class="mb-4">Vendor Applications</h2>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>No.</th>
                <th>Application No.</th>
                <th>Company Name</th>
                <th>Status</th>
                <th>Approved Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($vendors as $v): ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><?= htmlspecialchars($v['registration_number']); ?></td>
                <td><?= htmlspecialchars($v['company_name']); ?></td>
                <td>
                    <span class="badge 
                        <?= $v['status']=='Approved'?'bg-success':($v['status']=='Rejected'?'bg-danger':'bg-warning text-dark'); ?>">
                        <?= $v['status']; ?>
                    </span>
                </td>
                <td>
                    <?php if ($v['status']=="Approved" && $v['approved_at']): ?>
                        <?= date("F j, Y g:i A", strtotime($v['approved_at'])); ?>
                    <?php else: ?>
                        <em>-</em>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-info btn-sm viewVendor" data-id="<?= $v['id']; ?>">View</button>
                    <?php if ($v['status']=="Pending"): ?>
                        <a href="vendor_status.php?id=<?= $v['id']; ?>&action=approve" class="btn btn-success btn-sm">Accept</a>
                        <a href="vendor_status.php?id=<?= $v['id']; ?>&action=reject" class="btn btn-danger btn-sm">Decline</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Vendor Modal -->
<div class="modal fade" id="vendorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Vendor Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="vendorDetails">
        Loading...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
    $(".viewVendor").click(function(){
        var id = $(this).data("id");
        $("#vendorDetails").html("Loading...");
        $("#vendorModal").modal("show");

        $.get("vendor_view.php", {id: id}, function(data){
            $("#vendorDetails").html(data);
        });
    });
});
</script>
</body>
</html>
