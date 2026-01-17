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
    <h2 class="mb-4">🏥 Patient Insurance Cards</h2>

     <div class="main-sidebar">
        <?php include 'billing_sidebar.php'; ?>
    </div>

    <div class="row g-4">
        <?php
        $cards = $conn->query("
            SELECT *
            FROM patient_insurance
            WHERE status = 'Active'
            ORDER BY created_at DESC
        ");

        if ($cards->num_rows > 0):
            while ($c = $cards->fetch_assoc()):
        ?>
        <div class="col-md-4 d-flex justify-content-center">
            <div class="insurance-card"
                 style="background: <?= cardColor($c['insurance_company']); ?>">

                <div class="card-header">
                    <?= strtoupper($c['insurance_company']); ?>
                </div>

                <div class="chip"></div>

                <div class="insurance-number">
                    <?= $c['insurance_number']; ?>
                </div>

                <div class="patient-name mt-2">
                    <?= $c['full_name']; ?>
                </div>

                <small><?= $c['promo_name']; ?></small>

                <div class="card-footer d-flex justify-content-between">
                    <div>
                        <strong>Discount</strong><br>
                        <?= $c['discount_type'] === 'Percentage'
                            ? $c['discount_value'] . '%'
                            : '₱' . number_format($c['discount_value'], 2); ?>
                    </div>

                    <div>
                        <strong>Relation</strong><br>
                        <?= $c['relationship_to_insured']; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
            endwhile;
        else:
        ?>
        <div class="col-12">
            <div class="alert alert-warning text-center">
                No insurance cards available.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TABLE VIEW -->
    <hr class="my-5">

    <h4>Patient Insurance List</h4>
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
                <td><?= $row['full_name']; ?></td>
                <td><?= $row['insurance_company']; ?></td>
                <td><?= $row['insurance_number']; ?></td>
                <td><?= $row['promo_name']; ?></td>
                <td>
                    <?= $row['discount_type'] === 'Percentage'
                        ? $row['discount_value'] . '%'
                        : '₱' . number_format($row['discount_value'], 2); ?>
                </td>
                <td><?= $row['relationship_to_insured']; ?></td>
                <td>
                    <span class="badge bg-success"><?= $row['status']; ?></span>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

</div>

</body>
</html>
