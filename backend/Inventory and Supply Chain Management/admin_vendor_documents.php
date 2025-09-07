<<<<<<< HEAD
<?php
session_start();
require 'db.php';

// Fetch all vendor documents with vendor info
$stmt = $pdo->prepare("
    SELECT vd.*, v.company_name 
    FROM vendor_documents vd
    JOIN vendors v ON vd.vendor_id = v.id
    ORDER BY vd.uploaded_at DESC
");
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Vendor Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/Inventory_dashboard.css"> 
      <link rel="stylesheet" href="assets/css/vendor_documents.css"> 
  
</head>
<body class="bg-light">

    <!-- Sidebar -->
    <div class="sidebar">
        <?php include 'Inventory_dashboard.php'; ?>git add path/to/untracked-file.ext
    </div>

    <!-- Main Content -->
    <main class="admin-main">
        <h3 class="mb-3">All Vendors’ Uploaded Documents</h3>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">Vendor Documents</div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Vendor Name</th>
                            <th>Document Type</th>
                            <th>Uploaded At</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No documents uploaded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $i => $doc): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($doc['company_name']) ?></td>
                                    <td><?= htmlspecialchars($doc['doc_type']) ?></td>
                                    <td><?= htmlspecialchars($doc['uploaded_at']) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bi bi-file-earmark-text"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
=======
<?php
session_start();
require 'db.php';

// Fetch all vendor documents with vendor info
$stmt = $pdo->prepare("
    SELECT vd.*, v.company_name 
    FROM vendor_documents vd
    JOIN vendors v ON vd.vendor_id = v.id
    ORDER BY vd.uploaded_at DESC
");
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Vendor Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/Inventory_dashboard.css"> 
      <link rel="stylesheet" href="assets/css/vendor_documents.css"> 
  
</head>
<body class="bg-light">

    <!-- Sidebar -->
    <div class="sidebar">
        <?php include 'Inventory_dashboard.php'; ?>
    </div>

    <!-- Main Content -->
    <main class="admin-main">
        <h3 class="mb-3">All Vendors’ Uploaded Documents</h3>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">Vendor Documents</div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Vendor Name</th>
                            <th>Document Type</th>
                            <th>Uploaded At</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No documents uploaded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $i => $doc): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($doc['company_name']) ?></td>
                                    <td><?= htmlspecialchars($doc['doc_type']) ?></td>
                                    <td><?= htmlspecialchars($doc['uploaded_at']) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bi bi-file-earmark-text"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
>>>>>>> 7ab136995fe418cd34e9ba8d376e54938bd34398
