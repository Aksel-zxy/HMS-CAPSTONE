<?php
session_start();
require 'db.php';

if (!isset($_SESSION['vendor_id'])) {
    header("Location: vlogin.php");
    exit;
}

$vendor_id = $_SESSION['vendor_id'];
$page = "documents"; // ✅ so sidebar highlights the right menu

$stmt = $pdo->prepare("SELECT * FROM vendor_documents WHERE vendor_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$vendor_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/vendorsidebar.css"> <!-- Sidebar styles -->
    <link rel="stylesheet" href="assets/css/vendor_documents.css"> <!-- Page-specific styles -->
</head>
<body class="bg-light">

    <!-- Sidebar -->
    <div class="vendor-sidebar">
        <?php include 'vendorsidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <main class="vendor-main p-4">
        <h3 class="mb-3">My Uploaded Documents</h3>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">Documents Submitted</div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Document Type</th>
                            <th>Uploaded At</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No documents uploaded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $i => $doc): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
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

</body>
</html>
