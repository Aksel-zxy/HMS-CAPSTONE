<?php
session_start();
include '../../SQL/config.php';

// Fetch vendors
$stmt = $pdo->prepare("SELECT id, company_name FROM vendors ORDER BY company_name ASC");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all ratings
$stmt = $pdo->prepare("
    SELECT vr.vendor_id, vr.purchase_request_id, vr.rating, vr.feedback, vr.created_at AS feedback_date,
           MIN(vo.created_at) AS order_date
    FROM vendor_rating vr
    LEFT JOIN vendor_orders vo ON vr.purchase_request_id = vo.purchase_request_id AND vr.vendor_id = vo.vendor_id
    GROUP BY vr.vendor_id, vr.purchase_request_id, vr.rating, vr.feedback, vr.created_at
    ORDER BY vr.created_at DESC
");
$stmt->execute();
$allRatings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize ratings per vendor
$ratingsPerVendor = [];
foreach ($allRatings as $r) {
    $ratingsPerVendor[$r['vendor_id']][] = $r;
}

// Calculate average rating per vendor
$avgRatings = [];
foreach ($ratingsPerVendor as $vendor_id => $ratings) {
    $total = array_sum(array_column($ratings, 'rating'));
    $avgRatings[$vendor_id] = $total / count($ratings);
}

// Function to display stars with half-stars using Bootstrap icons
function displayStars($avg) {
    $fullStars = floor($avg);
    $halfStar = ($avg - $fullStars) >= 0.5 ? 1 : 0;
    $emptyStars = 5 - $fullStars - $halfStar;

    $html = '';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="bi bi-star-fill text-warning"></i> ';
    }
    if ($halfStar) {
        $html .= '<i class="bi bi-star-half text-warning"></i> ';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="bi bi-star text-warning"></i> ';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendor Ratings & Feedback</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/inventory_dashboard.css">

<style>
    body {
        font-family: Arial, sans-serif;
    }

    .container {
        margin-left: 270px; /* Prevent overlap with sidebar */
    }

    @media (max-width: 768px) {
        .container {
            margin-left: 0; /* On smaller screens, remove the margin */
        }
    }
</style>
</head>

<body class="bg-light">


<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">Vendor Ratings & Feedback</h2>

    <?php if (!empty($vendors)): ?>
    <table class="table table-bordered table-hover bg-white">
        <thead class="table-dark">
            <tr>
                <th>Vendor Name</th>
                <th>Average Rating</th>
                <th>Total Ratings</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vendors as $v): 
                $avg = $avgRatings[$v['id']] ?? 0;
                $ratings = $ratingsPerVendor[$v['id']] ?? [];
            ?>
            <tr>
                <td><?= htmlspecialchars($v['company_name']) ?></td>
                <td><?= displayStars($avg) ?> (<?= number_format($avg,1) ?>)</td>
                <td><?= count($ratings) ?></td>
                <td>
                    <?php if (!empty($ratings)): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#feedbackModal<?= $v['id'] ?>">View Feedbacks</button>
                    <?php else: ?>
                        <span class="text-muted">No feedback yet</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="alert alert-info">No vendors found.</div>
    <?php endif; ?>
</div>

<!-- Feedback Modals -->
<?php foreach ($vendors as $v): 
    $ratings = $ratingsPerVendor[$v['id']] ?? [];
    if (empty($ratings)) continue;
?>
<div class="modal fade" id="feedbackModal<?= $v['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Feedbacks for <?= htmlspecialchars($v['company_name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Rating</th>
                            <th>Feedback</th>
                            <th>Order Date</th>
                            <th>Feedback Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratings as $r): ?>
                        <tr>
                            <td>#<?= $r['purchase_request_id'] ?></td>
                            <td><?= displayStars($r['rating']) ?> (<?= $r['rating'] ?>)</td>
                            <td><?= htmlspecialchars($r['feedback']) ?></td>
                            <td><?= $r['order_date'] ? date("Y-m-d", strtotime($r['order_date'])) : 'N/A' ?></td>
                            <td><?= date("Y-m-d H:i", strtotime($r['feedback_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
