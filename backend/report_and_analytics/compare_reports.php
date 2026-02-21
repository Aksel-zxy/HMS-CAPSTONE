<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Compare Reports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Premium Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #eef1f6;
            font-family: "Poppins", sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, #0d6efd, #4f93ff);
            color: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.15);
            margin-bottom: 35px;
        }

        .compare-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 25px;
            border: 1px solid #e2e5ec;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            transition: .25s ease;
            cursor: pointer;
        }

        .compare-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.12);
        }

        .compare-icon {
            font-size: 2.4rem;
            color: #0d6efd;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <?
        include 'sidebar.php'
        ?>

        <div class="container py-4">

            <!-- HEADER -->
            <div class="page-header text-center">
                <h2 class="fw-bold mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Compare Monthly Reports</h2>
                <p class="mb-0 mt-2">Select a report category to compare trends between months.</p>
            </div>

            <!-- COMPARATOR CARDS -->
            <div class="row g-4">

                <div class="col-md-4">
                    <a href="month_attendance_comparitor.php" class="text-decoration-none text-dark">
                        <div class="compare-card text-center">
                            <i class="bi bi-people compare-icon"></i>
                            <h5 class="fw-semibold">Attendance Comparator</h5>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="month_billing_comparitor.php" class="text-decoration-none text-dark">
                        <div class="compare-card text-center">
                            <i class="bi bi-receipt compare-icon"></i>
                            <h5 class="fw-semibold">Billing Comparator</h5>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="month_budget_comparitor.php" class="text-decoration-none text-dark">
                        <div class="compare-card text-center">
                            <i class="bi bi-wallet2 compare-icon"></i>
                            <h5 class="fw-semibold">Budget Comparator</h5>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="month_claim_comparitor.php" class="text-decoration-none text-dark">
                        <div class="compare-card text-center">
                            <i class="bi bi-file-medical compare-icon"></i>
                            <h5 class="fw-semibold">Claim Comparator</h5>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="month_revenue_comparitor.php" class="text-decoration-none text-dark">
                        <div class="compare-card text-center">
                            <i class="bi bi-bar-chart-line compare-icon"></i>
                            <h5 class="fw-semibold">Revenue Comparator</h5>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="month_payroll_comparitor.php" class="text-decoration-none text-dark">
                        <div class="compare-card text-center">
                            <i class="bi bi-currency-dollar compare-icon"></i>
                            <h5 class="fw-semibold">Payroll Comparator</h5>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="month_pharmacy_sales_comparitor.php" class="text-decoration-none text-dark">
                        <div class="compare-card text-center">
                            <i class="bi bi-bag-heart compare-icon"></i>
                            <h5 class="fw-semibold">Pharmacy Sales Comparator</h5>
                        </div>
                    </a>
                </div>

            </div>

        </div>
    </div>
</body>

</html>