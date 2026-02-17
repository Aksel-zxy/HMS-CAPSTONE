<?php
include '../../SQL/config.php';

/* COLOR MAP PER INSURANCE COMPANY / PROMO */
function cardColor($company) {
    return match ($company) {
        'PhilHealth'   => 'linear-gradient(135deg, #1e3c72, #2a5298)',
        'Maxicare'     => 'linear-gradient(135deg, #0f9b8e, #38ef7d)',
        'Medicard'     => 'linear-gradient(135deg, #8e2de2, #4a00e0)',
        'Intellicare'  => 'linear-gradient(135deg, #f7971e, #ffd200)',
        default        => 'linear-gradient(135deg, #232526, #414345)',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Insurance Cards</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
/* =========================
   PAGE LAYOUT
========================= */
.content-wrapper {
    margin-left: 250px; /* Same as sidebar width */
    padding: 30px;
    transition: margin-left 0.3s ease;
}

/* When sidebar is closed */
.sidebar.closed ~ .content-wrapper {
    margin-left: 0;
}

/* Responsive for tablets/mobile */
@media (max-width: 768px) {
    .content-wrapper {
        margin-left: 0;
        padding: 15px;
    }
}

/* =========================
   INSURANCE CARD STYLING
========================= */
.insurance-card {
    width: 100%;
    max-width: 350px;
    height: 220px;
    border-radius: 18px;
    padding: 18px;
    color: #fff;
    position: relative;
    box-shadow: 0 10px 25px rgba(0,0,0,.3);
    overflow: hidden;
    margin-bottom: 20px;
}

.insurance-card::after {
    content: "";
    position: absolute;
    right: -40px;
    top: -40px;
    width: 140px;
    height: 140px;
    background: rgba(255,255,255,.15);
    border-radius: 50%;
}

.card-footer {
    position: absolute;
    bottom: 15px;
    width: calc(100% - 36px);
    font-size: 13px;
}

.chip {
    width: 45px;
    height: 35px;
    background: linear-gradient(135deg, #ffd700, #ffec8b);
    border-radius: 6px;
    margin-bottom: 10px;
}

/* Modal adjustments for mobile */
@media (max-width: 576px) {
    .insurance-card {
        max-width: 100%;
        height: auto;
        padding: 15px;
    }
    .card-footer {
        position: relative;
        bottom: auto;
        margin-top: 15px;
    }
}

/* Table styling */
.table thead {
    background-color: #007bff;
    color: white;
}

.table-responsive {
    overflow-x: auto;
}
</style>
</head>

<body>

<!-- Sidebar -->
<?php include 'billing_sidebar.php'; ?>

<!-- Main Content -->
<div class="content-wrapper">
<div class="container-fluid">
<h2 class="mb-4">🏥 Patient Insurance List</h2>

<div class="table-responsive">
<table class="table table-striped table-bordered align-middle">
<thead class="table-dark">
<tr>
<th>#</th>
<th>Name</th>
<th>Company</th>
<th>Insurance #</th>
<th>Promo</th>
<th>Discount</th>
<th>Relation</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php
$list = $conn->query("SELECT * FROM patient_insurance ORDER BY created_at DESC");
$i = 1;
while ($row = $list->fetch_assoc()):
?>
<tr>
<td><?= $i++; ?></td>
<td><?= htmlspecialchars($row['full_name']); ?></td>
<td><?= htmlspecialchars($row['insurance_company']); ?></td>
<td><?= htmlspecialchars($row['insurance_number']); ?></td>
<td><?= htmlspecialchars($row['promo_name']); ?></td>
<td>
<?= $row['discount_type'] === 'Percentage'
    ? htmlspecialchars($row['discount_value']) . '%'
    : '₱' . number_format($row['discount_value'], 2); ?>
</td>
<td><?= htmlspecialchars($row['relationship_to_insured']); ?></td>
<td>
<span class="badge bg-success"><?= htmlspecialchars($row['status']); ?></span>
</td>
<td>
<button type="button" class="btn btn-primary btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#cardModal<?= intval($row['patient_insurance_id']); ?>">
    View
</button>
</td>
</tr>

<!-- Modal -->
<div class="modal fade" id="cardModal<?= intval($row['patient_insurance_id']); ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Insurance Card - <?= htmlspecialchars($row['full_name']); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body d-flex justify-content-center">
        <div class="insurance-card" style="background: <?= cardColor($row['insurance_company']); ?>;">
            <div class="fw-bold"><?= strtoupper(htmlspecialchars($row['insurance_company'])); ?></div>
            <div class="chip"></div>
            <div class="fw-bold"><?= htmlspecialchars($row['insurance_number']); ?></div>
            <div class="mt-2 fw-bold text-uppercase"><?= htmlspecialchars($row['full_name']); ?></div>
            <small><?= htmlspecialchars($row['promo_name']); ?></small>
            <div class="card-footer d-flex justify-content-between">
                <div>
                    <strong>Discount</strong><br>
                    <?= $row['discount_type'] === 'Percentage'
                        ? htmlspecialchars($row['discount_value']) . '%'
                        : '₱' . number_format($row['discount_value'], 2); ?>
                </div>
                <div>
                    <strong>Relation</strong><br>
                    <?= htmlspecialchars($row['relationship_to_insured']); ?>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php endwhile; ?>
</tbody>
</table>
</div>

</div>
</div>

</body>
</html>
