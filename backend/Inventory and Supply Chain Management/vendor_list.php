<?php
require 'db.php'; // $pdo connection

// Fetch all approved vendors
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE status = 'Approved' ORDER BY company_name ASC");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approved Vendors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">



<div class="main-sidebar">
        <?php include 'Inventory_dashboard.php'; ?>
    </div>


<div class="container py-5">
    <h2 class="mb-4"> Vendor List</h2>
    
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Company Name</th>
                <th>Primary Category</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($vendors as $i => $vendor): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($vendor['company_name']) ?></td>
                <td><?= htmlspecialchars($vendor['primary_product_categories']) ?></td>
                <td><?= htmlspecialchars($vendor['contact_name']) ?></td>
                <td><?= htmlspecialchars($vendor['email']) ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" 
                        data-bs-toggle="modal" 
                        data-bs-target="#vendorModal<?= $vendor['id'] ?>">
                        View Details
                    </button>
                </td>
            </tr>

            <!-- Modal -->
            <div class="modal fade" id="vendorModal<?= $vendor['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title"><?= htmlspecialchars($vendor['company_name']) ?> - Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                      <p><strong>Registration #:</strong> <?= htmlspecialchars($vendor['registration_number']) ?></p>
                      <p><strong>Company Address:</strong> <?= htmlspecialchars($vendor['company_address']) ?></p>
                      <p><strong>Contact Person:</strong> <?= htmlspecialchars($vendor['contact_name']) ?> (<?= htmlspecialchars($vendor['contact_title']) ?>)</p>
                      <p><strong>Phone:</strong> <?= htmlspecialchars($vendor['phone']) ?></p>
                      <p><strong>Email:</strong> <?= htmlspecialchars($vendor['email']) ?></p>
                      <p><strong>TIN/VAT:</strong> <?= htmlspecialchars($vendor['tin_vat']) ?></p>
                      <p><strong>Primary Category:</strong> <?= htmlspecialchars($vendor['primary_product_categories']) ?></p>
                      <p><strong>Country:</strong> <?= htmlspecialchars($vendor['country']) ?></p>
                      <p><strong>Website:</strong> <a href="<?= htmlspecialchars($vendor['website']) ?>" target="_blank"><?= htmlspecialchars($vendor['website']) ?></a></p>
                      <p><strong>Approved At:</strong> <?= htmlspecialchars($vendor['approved_at']) ?></p>
                      <p><strong>Contract End Date:</strong> <?= htmlspecialchars($vendor['contract_end_date']) ?></p>
                    </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
