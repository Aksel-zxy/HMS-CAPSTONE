<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Hospital Monthly Payroll Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            font-family: "Poppins", sans-serif;
        }

        .report-card {
            border-radius: 16px;
            border: 1px solid #ddd;
            background: #fff;
            padding: 30px;
            margin-top: 20px;
        }

        .stat-title {
            font-weight: 600;
            color: #495057;
        }

        .stat-value {
            font-weight: 700;
            font-size: 1.3rem;
        }

        .stat-card {
            background: #e9ecef;
            border-radius: 12px;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        <div class="container my-5">

            <h3 class="fw-bold mb-4 text-center">💰 Hospital Monthly Payroll Report</h3>

            <!-- Month/Year Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <input type="number" id="month" class="form-control" value="12" min="1" max="12">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <input type="number" id="year" class="form-control" value="2025">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-dark w-100" onclick="loadPayroll()">Load Report</button>
                </div>
            </div>

            <!-- Payroll Report Card -->
            <div id="payrollReport" class="report-card text-center">
                <p class="text-muted">No data loaded</p>
            </div>

        </div>
    </div>

    <script>
        function formatCurrency(amount) {
            return amount.toLocaleString('en-US', {
                style: 'currency',
                currency: 'USD'
            });
        }

        async function loadPayroll() {
            const month = document.getElementById("month").value;
            const year = document.getElementById("year").value;

            const reportDiv = document.getElementById("payrollReport");
            reportDiv.innerHTML = `<p>Loading...</p>`;

            try {
                const res = await fetch(`https://localhost:7212/payroll/getHospitalMonthlyPayrollReport?month=${month}&year=${year}`);
                if (!res.ok) throw new Error("Failed to fetch payroll report");

                const data = await res.json();

                reportDiv.innerHTML = `
                    <div class="stat-card">
                        <h5 class="fw-bold mb-3">Month: ${data.month}, Year: ${data.year}</h5>
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <div class="stat-title">Total Employees</div>
                                <div class="stat-value">${data.total_employees}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-title">Total Gross Pay</div>
                                <div class="stat-value">${formatCurrency(data.total_gross_pay)}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-title">Total Deductions</div>
                                <div class="stat-value">${formatCurrency(data.total_deductions)}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-title">Total Net Pay</div>
                                <div class="stat-value">${formatCurrency(data.total_net_pay)}</div>
                            </div>
                        </div>
                    </div>
                `;

            } catch (err) {
                console.error(err);
                reportDiv.innerHTML = `<p class="text-danger">Failed to load payroll report</p>`;
            }
        }

        // Load default report
        loadPayroll();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>