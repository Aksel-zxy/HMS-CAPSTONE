<?php
include 'header.php';

include '../../SQL/config.php';

if (!isset($_SESSION['report']) || $_SESSION['report'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f4f5f9;
            font-family: "Poppins", sans-serif;
        }

        /* =====================================================
           PREMIUM GLASS SIDEBAR (OPTION B, width 420px)
        ===================================================== */
        .analytics-sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 420px;
            height: 100vh;
            padding: 25px;

            /* Glass Frosted */
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(18px);

            border-left: 1px solid rgba(255, 255, 255, 0.45);
            box-shadow: -4px 0 25px rgba(0, 0, 0, 0.10);

            overflow-y: auto;
            z-index: 30;
        }

        .main-content {
            margin-right: 440px;
        }

        .analytics-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #222;
        }

        .stats-block {
            background: rgba(255, 255, 255, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.45);
            padding: 14px;
            border-radius: 14px;
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-weight: 600;
            color: #333;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }

        .chart-container {
            height: 220px;
        }

        .frost-divider {
            height: 2px;
            background: linear-gradient(to right, #007bff, #007bff20);
            margin: 20px 0;
        }

        /* Insurance Section (Glass) */
        .insurance-box {
            background: rgba(255, 255, 255, 0.55);
            border-radius: 14px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.45);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.07);
            margin-top: 10px;
        }

        .ins-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #111;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ins-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-weight: 600;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .ins-row:last-child {
            border-bottom: none;
        }

        .form-select {
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(0, 0, 0, 0.2);
            color: #222;
            border-radius: 10px;
        }

        .report-card {
            border-radius: 14px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            text-align: center;
            transition: .25s;
        }

        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .report-icon {
            font-size: 2rem;
            color: #0d6efd;
        }

        .section-title {
            margin-top: 25px;
            font-weight: 600;
            font-size: 1.15rem;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="container py-4 main-content">

            <h2 class="fw-bold mb-4">Reports & Analytics Dashboard</h2>

            <!-- FORECAST ANALYTICS -->
            <div class="section-title">Forecast Analytics</div>
            <div class="row g-3">

                <div class="col-md-3">
                    <a href="bed_occupancy_forecast.php" class="text-decoration-none text-dark">
                        <div class="report-card">
                            <i class="bi bi-hospital report-icon"></i>
                            <div class="report-title">Bed Occupancy Forecast</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="claims_forecast.php" class="text-decoration-none text-dark">
                        <div class="report-card">
                            <i class="bi bi-clipboard-check report-icon"></i>
                            <div class="report-title">Claims Forecast</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="medicine_shortage_forecast.php" class="text-decoration-none text-dark">
                        <div class="report-card">
                            <i class="bi bi-capsule report-icon"></i>
                            <div class="report-title">Medicine Shortage Forecast</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="month_cost_management_forecast_result.php" class="text-decoration-none text-dark">
                        <div class="report-card">
                            <i class="bi bi-cash-stack report-icon"></i>
                            <div class="report-title">Cost Management Forecast</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="month_patient_admission_forecast.php" class="text-decoration-none text-dark">
                        <div class="report-card">
                            <i class="bi bi-person-lines-fill report-icon"></i>
                            <div class="report-title">Patient Admission Forecast</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="revenue_forecast_result.php" class="text-decoration-none text-dark">
                        <div class="report-card">
                            <i class="bi bi-bar-chart-line report-icon"></i>
                            <div class="report-title">Revenue Forecast</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- YEARLY REPORTS -->
            <div class="section-title">📅 Yearly Reports</div>
            <div class="row g-3">

                <div class="col-md-3">
                    <a href="year_attendance_report.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-people report-icon"></i>
                            <div class="report-title">Attendance Report</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="year_beds_summary_report.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-hospital report-icon"></i>
                            <div class="report-title">Beds Summary</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="year_claim_report.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-file-medical report-icon"></i>
                            <div class="report-title">Claim Report</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="year_department_budget_summary.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-wallet2 report-icon"></i>
                            <div class="report-title">Dept. Budget Summary</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="year_payroll_summary.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-currency-dollar report-icon"></i>
                            <div class="report-title">Payroll Summary</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="year_pharmacy_sales_report.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-bag-heart report-icon"></i>
                            <div class="report-title">Pharmacy Sales</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="yearly_billing_report.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-receipt report-icon"></i>
                            <div class="report-title">Billing Report</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- GENERAL REPORTS -->
            <div class="section-title">📁 General Reports</div>
            <div class="row g-3">

                <div class="col-md-3">
                    <a href="staff_information.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-person-badge report-icon"></i>
                            <div class="report-title">Staff Information</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="shift_and_duty.php" class="text-decoration-none text-dark">
                        <div class="report-card"><i class="bi bi-calendar-check report-icon"></i>
                            <div class="report-title">Shift & Duty Monitoring</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="shift_and_duty.php" class="text-decoration-none text-dark">
                        <div class="report-card d-flex flex-column align-items-center p-3 shadow-sm border rounded">
                            <i class="bi bi-calendar-check report-icon fs-1 mb-2 text-primary"></i>
                            <div class="report-title fw-semibold text-center">
                                Shift & Duty Monitoring
                            </div>
                        </div>
                    </a>
                </div>

            </div>

        </div><!-- END MAIN CONTENT -->



        <!-- =====================================================
             PREMIUM GLASS RIGHT SIDEBAR
        ===================================================== -->
        <div class="analytics-sidebar">

            <!-- PATIENT ANALYTICS -->
            <div class="analytics-title"><i class="bi bi-activity"></i> Patient Analytics</div>

            <div class="stats-block"><span>Avg Age</span><span id="avgAge">-</span></div>
            <div class="stats-block"><span>Male</span><span id="maleCount">-</span></div>
            <div class="stats-block"><span>Female</span><span id="femaleCount">-</span></div>

            <div class="chart-container">
                <canvas id="ageChart"></canvas>
            </div>

            <div class="frost-divider"></div>

            <!-- INSURANCE ANALYTICS -->
            <div class="ins-title"><i class="bi bi-shield-check"></i> Insurance Summary</div>

            <div class="insurance-box">

                <select class="form-select mb-3" id="insuranceYearSelector"></select>

                <div style="height:200px; margin-bottom:15px;">
                    <canvas id="insuranceChart"></canvas>
                </div>

                <div class="frost-divider"></div>

                <div class="ins-row"><span>Total Approved Payout</span><span id="insApprovedPayout">-</span></div>
                <div class="ins-row"><span>Total Hospital Loss</span><span id="insLoss" class="text-danger">-</span></div>
            </div>

            <!-- Compare Reports Button -->
            <div class="mt-4">
                <a href="compare_reports.php" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-bar-chart-steps me-2"></i> Compare Month Reports
                </a>
            </div>

        </div><!-- END SIDEBAR -->
    </div> <!-- END FLEX CONTAINER -->



    <!-- ================================================
         JAVASCRIPT – ANALYTICS MODULES
    ================================================= -->
    <script>
        /* ------------------------------------
           PATIENT ANALYTICS
        ------------------------------------ */
        let ageChart = null;

        async function loadPatientAnalytics() {
            try {
                const res = await fetch("https://bsis-03.keikaizen.xyz/patient/patientDetails?page=1&size=500");
                const data = await res.json();

                avgAge.innerText = data.averageAge ?? "-";
                maleCount.innerText = data.maleCount ?? "-";
                femaleCount.innerText = data.femaleCount ?? "-";

                drawAgeChart(data.ages ?? []);
            } catch (err) {
                console.error("Patient Analytics Error:", err);
            }
        }

        function drawAgeChart(ages) {
            const groups = {
                "0-20": 0,
                "21-40": 0,
                "41-60": 0,
                "60+": 0
            };

            ages.forEach(a => {
                if (a.age <= 20) groups["0-20"]++;
                else if (a.age <= 40) groups["21-40"]++;
                else if (a.age <= 60) groups["41-60"]++;
                else groups["60+"]++;
            });

            const ctx = document.getElementById("ageChart");

            if (ageChart) ageChart.destroy();

            ageChart = new Chart(ctx, {
                type: "doughnut",
                data: {
                    labels: Object.keys(groups),
                    datasets: [{
                        data: Object.values(groups),
                        backgroundColor: ["#0d6efd", "#20c997", "#ffc107", "#dc3545"]
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: "bottom"
                        }
                    }
                }
            });
        }


        /* ------------------------------------
           INSURANCE ANALYTICS
        ------------------------------------ */
        let insuranceChart = null;
        const insuranceApi = "https://bsis-03.keikaizen.xyz/insurance/yearInsuranceSummaryDetails?year=";

        async function loadInsuranceAnalytics(year) {
            try {
                const res = await fetch(insuranceApi + year);
                const data = await res.json();

                const approved = data.totalClaimApproved;
                const denied = data.totalClaimDenied;

                const total = approved + denied;
                const approvePct = total ? (approved / total * 100).toFixed(1) : 0;
                const denyPct = total ? (denied / total * 100).toFixed(1) : 0;

                insApprovedPayout.innerText = "₱" + data.totalApprovePayoutAmount.toLocaleString();
                insLoss.innerText = "₱" + data.totalHospitalLoss.toLocaleString();

                const ctx = document.getElementById("insuranceChart");

                if (insuranceChart) insuranceChart.destroy();

                insuranceChart = new Chart(ctx, {
                    type: "doughnut",
                    data: {
                        labels: [
                            `Approved (${approvePct}%)`,
                            `Denied (${denyPct}%)`
                        ],
                        datasets: [{
                            data: [approved, denied],
                            backgroundColor: ["#20c997", "#dc3545"]
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                position: "bottom"
                            }
                        }
                    }
                });

            } catch (err) {
                console.error("Insurance Analytics Error:", err);
            }
        }

        /* ------------------------------------
           INITIAL PAGE LOAD
        ------------------------------------ */
        document.addEventListener("DOMContentLoaded", () => {

            loadPatientAnalytics();

            const insSelect = document.getElementById("insuranceYearSelector");
            const currentYear = new Date().getFullYear();

            for (let y = currentYear; y >= currentYear - 5; y--) {
                insSelect.innerHTML += `<option value="${y}">${y}</option>`;
            }

            insSelect.value = currentYear;
            loadInsuranceAnalytics(currentYear);

            insSelect.addEventListener("change", () =>
                loadInsuranceAnalytics(insSelect.value)
            );
        });
    </script>

</body>

</html>