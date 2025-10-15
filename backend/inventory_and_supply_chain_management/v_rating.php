<?php
session_start();
include '../../SQL/config.php';

// ✅ Ensure vendor is logged in
if (!isset($_SESSION['vendor_id'])) {
    header("Location: vlogin.php");
    exit;
}

$vendor_id = $_SESSION['vendor_id'];

// Fetch logged-in vendor info
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vendor) {
    die("Vendor not found.");
}

// Fetch ratings & feedback for this vendor only
$stmt = $pdo->prepare("
    SELECT vr.purchase_request_id, vr.rating, vr.feedback, vr.created_at AS feedback_date,
           MIN(vo.created_at) AS order_date
    FROM vendor_rating vr
    LEFT JOIN vendor_orders vo 
        ON vr.purchase_request_id = vo.purchase_request_id 
       AND vr.vendor_id = vo.vendor_id
    WHERE vr.vendor_id = ?
    GROUP BY vr.purchase_request_id, vr.rating, vr.feedback, vr.created_at
    ORDER BY vr.created_at DESC
");
$stmt->execute([$vendor_id]);
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$avgRating = !empty($ratings) ? array_sum(array_column($ratings,'rating'))/count($ratings) : 0;

// Function to display stars
function displayStars($avg){
    $fullStars = floor($avg);
    $halfStar = ($avg - $fullStars) >= 0.5 ? 1 : 0;
    $emptyStars = 5 - $fullStars - $halfStar;
    $html = '';
    for ($i=0;$i<$fullStars;$i++) $html .= '<i class="bi bi-star-fill text-warning"></i> ';
    if ($halfStar) $html .= '<i class="bi bi-star-half text-warning"></i> ';
    for ($i=0;$i<$emptyStars;$i++) $html .= '<i class="bi bi-star text-warning"></i> ';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Ratings & Feedback</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/vendorsidebar.css">
<link rel="stylesheet" href="assets/css/v_rating.css">

</head>
<body class="bg-light">

<!-- Sidebar -->
<div class="vendor-sidebar">
    <?php include 'vendorsidebar.php'; ?>
</div>

<!-- Main Content -->
<main class="vendor-main p-4">
    <h2 class="mb-4">My Ratings & Feedback</h2>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($vendor['company_name']) ?></h5>
            <p class="card-text">
                Average Rating: <?= displayStars($avgRating) ?> (<?= number_format($avgRating,1) ?>) <br>
                Total Ratings: <?= count($ratings) ?>
            </p>
        </div>
    </div>

    <?php if(!empty($ratings)): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">All Feedbacks</div>
        <div class="card-body">
            <table class="table table-bordered table-hover">
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
                    <?php foreach($ratings as $r): ?>
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
    </div>
    <?php else: ?>
        <div class="alert alert-info">You have not received any feedback yet.</div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
