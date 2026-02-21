<?php
// Get month and year from query string or use current
$month = $_GET['month'] ?? date('n');
$year  = $_GET['year'] ?? date('Y');

// ✅ Adjusted API endpoint
$apiUrl = "https://bsis-03.keikaizen.xyz/journal/getMonthTotalRevenueForecast?month=$month&year=$year";

$response = null;
$error = null;

// Initialize CURL
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$result = curl_exec($ch);

if (curl_errno($ch)) {
    $error = curl_error($ch);
} else {
    $response = json_decode($result, true);
}

curl_close($ch);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Revenue Forecast</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .card {
            border-radius: 14px;
        }

        .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .metric-label {
            color: #6c757d;
            font-size: .9rem;
        }
    </style>
</head>

<body>
    <div class="container py-5">

        <!-- Header -->
        <div class="mb-4">
            <h3 class="fw-bold">📈 Monthly Revenue Forecast</h3>
            <p class="text-muted mb-0">AI-generated financial projections</p>
        </div>

        <!-- Filters -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <select name="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Year</label>
                <input type="number" name="year" class="form-control" value="<?= $year ?>">
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary w-100">
                    View Forecast
                </button>
            </div>
        </form>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($response): ?>

            <!-- Forecast Cards -->
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="metric-label">Total Revenue</div>
                            <div class="metric-value text-success">
                                ₱<?= number_format($response['total_revenue'], 2) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="metric-label">Total Transactions</div>
                            <div class="metric-value">
                                <?= number_format($response['pharmacy_total_transactions']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="metric-label">Average Bill Amount</div>
                            <div class="metric-value text-primary">
                                ₱<?= number_format($response['average_bill_amount'], 2) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="text-muted mt-4 small">
                Forecast generated for <strong><?= date('F', mktime(0, 0, 0, $month, 1)) ?> <?= $year ?></strong>
            </div>

        <?php endif; ?>

    </div>
</body>

</html>