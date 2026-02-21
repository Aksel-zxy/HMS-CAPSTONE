<?php
// month_attendance_report.php

// Get month and year from query parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n'); // default current month
$year  = isset($_GET['year'])  ? intval($_GET['year'])  : date('Y'); // default current year

$monthNames = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December"
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Attendance Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f7f7f7;
        }

        .card-metrics {
            border-radius: 10px;
        }

        .small-text {
            font-size: .85rem;
            color: #6c757d;
        }

        canvas {
            max-height: 300px;
        }

        #summaryText {
            font-size: 1rem;
            margin-top: 1rem;
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
        }

        .back-btn {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container py-4">

        <!-- Back Button -->
        <a href="https://bsis-03.keikaizen.xyz/backend/report_and_analytics/year_attendance_report.php" class="btn btn-secondary back-btn">
            <i class="bi bi-arrow-left"></i> Back
        </a>

        <h4 class="fw-semibold mb-4 text-center">
            Attendance Report for <?= $monthNames[$month - 1] ?> <?= $year ?>
        </h4>

        <!-- TOP SUMMARY -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="card p-3 card-metrics">
                    <div class="small-text">Present</div>
                    <h5 id="totalPresent">0</h5>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3 card-metrics">
                    <div class="small-text">Absent</div>
                    <h5 id="totalAbsent">0</h5>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3 card-metrics">
                    <div class="small-text">Late</div>
                    <h5 id="totalLate">0</h5>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3 card-metrics">
                    <div class="small-text">Leave</div>
                    <h5 id="totalLeave">0</h5>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card p-3 card-metrics">
                    <div class="small-text">Undertime</div>
                    <h5 id="totalUndertime">0</h5>
                </div>
            </div>
        </div>

        <!-- ATTENDANCE SUMMARY CHART -->
        <div class="card p-4 mb-4">
            <canvas id="monthlyChart"></canvas>
            <div id="summaryText">Loading summary...</div>
        </div>
    </div>

    <script>
        const month = <?= $month ?>;
        const year = <?= $year ?>;

        const apiUrl = `https://bsis-03.keikaizen.xyz/employee/getMonthAttendanceReport/${month}/${year}`;

        function updateReport(url) {
            fetch(url)
                .then(res => res.json())
                .then(data => {

                    // Update top metrics
                    document.getElementById("totalPresent").innerText = data.present;
                    document.getElementById("totalAbsent").innerText = data.absent;
                    document.getElementById("totalLate").innerText = data.late;
                    document.getElementById("totalLeave").innerText = data.leave;
                    document.getElementById("totalUndertime").innerText = data.underTime;

                    // Chart data
                    const labels = ['Present', 'Absent', 'Late', 'Leave', 'Undertime'];
                    const totals = [
                        data.present,
                        data.absent,
                        data.late,
                        data.leave,
                        data.underTime
                    ];
                    const colors = ['#198754', '#dc3545', '#ffc107', '#0dcaf0', '#6f42c1'];

                    const ctx = document.getElementById("monthlyChart").getContext('2d');

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: `Attendance Summary`,
                                data: totals,
                                backgroundColor: colors
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                title: {
                                    display: true,
                                    text: `Attendance Summary for <?= $monthNames[$month - 1] ?> <?= $year ?>`
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });

                    // Generate text summary
                    let summary = `In <?= $monthNames[$month - 1] ?> <?= $year ?>: `;
                    summary += `Most employees (${data.present}) were present. `;
                    summary += `${data.absent} were absent, `;
                    summary += `${data.late} were late, `;
                    summary += `${data.leave} took leave, `;
                    summary += `and ${data.underTime} had undertime.`;

                    document.getElementById("summaryText").innerText = summary;

                })
                .catch(err => console.error('Error fetching monthly report:', err));
        }

        // Load report automatically
        updateReport(apiUrl);
    </script>
</body>

</html>