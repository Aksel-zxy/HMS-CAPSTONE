<?php
// vendorprofile.php
session_start();
include '../../SQL/config.php';

// ---- 1) Determine vendor id ----
$vendor_id = 0;
if (isset($_GET['id'])) {
    $vendor_id = (int) $_GET['id'];
} elseif (isset($_SESSION['vendor_id'])) {
    $vendor_id = (int) $_SESSION['vendor_id'];
}
if ($vendor_id <= 0) {
    die("Missing vendor id.");
}

// ---- 2) Handle update form ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "UPDATE vendors SET 
                company_name = :company_name,
                company_address = :company_address,
                contact_name = :contact_name,
                contact_title = :contact_title,
                phone = :phone,
                email = :email,
                tin_vat = :tin_vat,
                primary_product_categories = :primary_product_categories,
                website = :website
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([
        ':company_name'               => $_POST['company_name'] ?? '',
        ':company_address'            => $_POST['company_address'] ?? '',
        ':contact_name'               => $_POST['contact_name'] ?? '',
        ':contact_title'              => $_POST['contact_title'] ?? '',
        ':phone'                      => $_POST['phone'] ?? '',
        ':email'                      => $_POST['email'] ?? '',
        ':tin_vat'                    => $_POST['tin_vat'] ?? '',
        ':primary_product_categories' => $_POST['primary_product_categories'] ?? '',
        ':website'                    => $_POST['website'] ?? '',
        ':id'                         => $vendor_id,
    ]);

    header("Location: vendorprofile.php?id={$vendor_id}&updated=" . ($ok ? '1' : '0'));
    exit;
}

// ---- 3) Fetch vendor ----
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vendor) {
    die("Vendor not found.");
}

// Helper for HTML escaping
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vendor Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="vendor-sidebar">
        <?php include 'vendorsidebar.php'; ?>
    </div>


<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Vendor Profile</h2>
    <a href="vendors_list.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <?php if (isset($_GET['updated'])): ?>
    <?php if ($_GET['updated'] === '1'): ?>
      <div class="alert alert-success">Profile updated successfully.</div>
    <?php else: ?>
      <div class="alert alert-danger">Update failed.</div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong><?= e($vendor['company_name']) ?></strong>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit Profile</button>
    </div>
    <div class="card-body">
      <table class="table table-bordered">
        <tr><th>Registration Number</th><td><?= e($vendor['registration_number']) ?></td></tr>
        <tr><th>Company Name</th><td><?= e($vendor['company_name']) ?></td></tr>
        <tr><th>Company Address</th><td><?= nl2br(e($vendor['company_address'])) ?></td></tr>
        <tr><th>Contact Name</th><td><?= e($vendor['contact_name']) ?></td></tr>
        <tr><th>Contact Title</th><td><?= e($vendor['contact_title']) ?></td></tr>
        <tr><th>Phone</th><td><?= e($vendor['phone']) ?></td></tr>
        <tr><th>Email</th><td><?= e($vendor['email']) ?></td></tr>
        <tr><th>TIN/VAT</th><td><?= e($vendor['tin_vat']) ?></td></tr>
        <tr><th>Primary Categories</th><td><?= e($vendor['primary_product_categories']) ?></td></tr>
        <tr><th>Country</th><td><?= e($vendor['country']) ?></td></tr>
        <tr><th>Website</th><td><?= e($vendor['website']) ?></td></tr>
        <tr><th>Status</th><td><?= e($vendor['status']) ?></td></tr>
        <tr><th>Created At</th><td><?= e($vendor['created_at']) ?></td></tr>
      </table>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Edit Vendor Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">

          <!-- Registration number (read-only) -->
          <div class="col-md-6">
            <label class="form-label">Registration Number</label>
            <input type="text" class="form-control" value="<?= e($vendor['registration_number']) ?>" disabled>
          </div>

          <!-- Company Name -->
          <div class="col-md-6">
            <label class="form-label">Company Name</label>
            <input type="text" name="company_name" class="form-control" value="<?= e($vendor['company_name']) ?>" required>
          </div>

          <!-- Company Address -->
          <div class="col-md-12">
            <label class="form-label">Company Address</label>
            <textarea name="company_address" class="form-control" required><?= e($vendor['company_address']) ?></textarea>
          </div>

          <!-- Contact Name -->
          <div class="col-md-6">
            <label class="form-label">Contact Name</label>
            <input type="text" name="contact_name" class="form-control" value="<?= e($vendor['contact_name']) ?>" required>
          </div>

          <!-- Contact Title -->
          <div class="col-md-6">
            <label class="form-label">Contact Title</label>
            <input type="text" name="contact_title" class="form-control" value="<?= e($vendor['contact_title']) ?>">
          </div>

          <!-- Phone -->
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($vendor['phone']) ?>" required>
          </div>

          <!-- Email -->
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($vendor['email']) ?>" required>
          </div>

          <!-- TIN/VAT -->
          <div class="col-md-6">
            <label class="form-label">TIN/VAT</label>
            <input type="text" name="tin_vat" class="form-control" value="<?= e($vendor['tin_vat']) ?>" required>
          </div>

          <!-- Categories -->
          <div class="col-md-6">
            <label class="form-label">Primary Categories</label>
            <input type="text" name="primary_product_categories" class="form-control" value="<?= e($vendor['primary_product_categories']) ?>" required>
          </div>

          <!-- Country (read-only) -->
          <div class="col-md-6">
            <label class="form-label">Country</label>
            <input type="text" class="form-control" value="<?= e($vendor['country']) ?>" disabled>
          </div>

          <!-- Website -->
          <div class="col-md-6">
            <label class="form-label">Website</label>
            <input type="text" name="website" class="form-control" value="<?= e($vendor['website']) ?>">
          </div>

          <!-- Status (read-only) -->
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <input type="text" class="form-control" value="<?= e($vendor['status']) ?>" disabled>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
