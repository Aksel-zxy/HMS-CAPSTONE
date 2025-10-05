<?php
require 'db.php';

if (!isset($_GET['id'])) { die("Vendor ID required."); }
$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id=?");
$stmt->execute([$id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) { die("Vendor not found."); }

$docs = $pdo->prepare("SELECT * FROM vendor_documents WHERE vendor_id=?");
$docs->execute([$id]);
$documents = $docs->fetchAll(PDO::FETCH_ASSOC);

$acks = $pdo->prepare("SELECT * FROM vendor_acknowledgments WHERE vendor_id=?");
$acks->execute([$id]);
$ack = $acks->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendor Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h2>Vendor Details</h2>
    <a href="vendor_application.php" class="btn btn-secondary mb-3">⬅ Back to Applications</a>
    
    <div class="card mb-4">
        <div class="card-header">Profile</div>
        <div class="card-body">
            <p><strong>Application No:</strong> <?= $vendor['registration_number']; ?></p>
            <p><strong>Company Name:</strong> <?= htmlspecialchars($vendor['company_name']); ?></p>
            <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($vendor['company_address'])); ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($vendor['contact_name'])." (".$vendor['contact_title'].")"; ?></p>
            <p><strong>Phone:</strong> <?= $vendor['phone']; ?></p>
            <p><strong>Email:</strong> <?= $vendor['email']; ?></p>
            <p><strong>TIN/VAT:</strong> <?= $vendor['tin_vat']; ?></p>
            <p><strong>Categories:</strong> <?= $vendor['primary_product_categories']; ?></p>
            <p><strong>Country:</strong> <?= $vendor['country']; ?></p>
            <p><strong>Website:</strong> <?= $vendor['website']; ?></p>
            <p><strong>Status:</strong> <span class="badge bg-primary"><?= $vendor['status']; ?></span></p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Documents</div>
        <div class="card-body">
            <?php if ($documents): ?>
                <ul>
                    <?php foreach ($documents as $d): ?>
                        <li><a href="<?= htmlspecialchars($d['file_path']); ?>" target="_blank">📄 <?= basename($d['file_path']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No documents uploaded.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Acknowledgments</div>
        <div class="card-body">
            <?php if ($ack): ?>
                <p><strong>Authorized Name:</strong> <?= $ack['authorized_name']; ?></p>
                <p><strong>Authorized Title:</strong> <?= $ack['authorized_title']; ?></p>
                <p><strong>Signed Date:</strong> <?= $ack['signed_date']; ?></p>
                <hr>
                <p><strong>Accepted Policies:</strong></p>
                <ul>
                    <?php foreach ($ack as $key=>$val): 
                        if (in_array($key,['vendor_id','authorized_name','authorized_title','signed_date'])) continue;
                        echo "<li>".ucwords(str_replace("_"," ",$key)).": ".($val?' Yes':' No')."</li>";
                    endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No acknowledgment submitted.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
