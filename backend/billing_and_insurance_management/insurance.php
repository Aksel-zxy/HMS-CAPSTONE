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
<html>
<head>
    <title>Patient Insurance Cards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        body {
            background: #f4f6f9;
        }

        /* CARD SIZE LIKE REAL ID */
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

        .card-header {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .insurance-number {
            font-size: 15px;
            letter-spacing: 1px;
            font-weight: bold;
        }

        .patient-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
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
    </style>
</head>
<body>

<div class="container mt-4">
    <h2 class="mb-4">🏥 Patient Insurance List</h2>

    <div class="main-sidebar mb-4">
        <?php include 'billing_sidebar.php'; ?>
    </div>

    <table class="table table-striped table-bordered">
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
                    <!-- View Button triggers modal -->
                    <button type="button" class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#cardModal<?= intval($row['patient_insurance_id']); ?>">
                        View
                    </button>
                </td>
            </tr>

            <!-- Modal for viewing insurance card -->
            <div class="modal fade" id="cardModal<?= intval($row['patient_insurance_id']); ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Insurance Card - <?= htmlspecialchars($row['full_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body d-flex justify-content-center">
                    <div class="insurance-card" style="background: <?= cardColor($row['insurance_company']); ?>;">
                        <div class="card-header"><?= strtoupper(htmlspecialchars($row['insurance_company'])); ?></div>
                        <div class="chip"></div>
                        <div class="insurance-number"><?= htmlspecialchars($row['insurance_number']); ?></div>
                        <div class="patient-name mt-2"><?= htmlspecialchars($row['full_name']); ?></div>
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

</body>
</html>
