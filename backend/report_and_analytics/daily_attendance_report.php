<?php
include 'header.php'
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<div class="d-flex">
    <!-- Sidebar -->
    <?php include 'sidebar.php' ?>

    <body class="bg-light">
        <div class="container py-5">
            <!-- Page Title -->
            <div class="mb-4 text-center">
                <h2 class="fw-bold text-dark">
                    <i class="bi bi-calendar-check-fill text-primary me-2"></i> Daily Attendance Report
                </h2>
            </div>

            <!-- Back Button -->
            <div class="mb-4 text-start">
                <a href="month_attendance_report.php" class="btn btn-outline-primary rounded-pill shadow-sm">
                    <i class="bi bi-arrow-left-circle me-2"></i> Back to Monthly Report
                </a>
            </div>

            <div class="row g-4">
                <!-- Summary Table -->
                <div class="col-md-4">
                    <div class="card shadow border-0">
                        <div class="card-body">
                            <h5 class="card-title mb-3 text-primary fw-bold">
                                <i class="bi bi-bar-chart-fill me-2"></i> Attendance Summary
                            </h5>

                            <table class="table table-hover align-middle text-center mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><span class="badge rounded-pill bg-success">Present</span></th>
                                        <th><span class="badge rounded-pill bg-danger">Absent</span></th>
                                        <th><span class="badge rounded-pill bg-warning text-dark">Late</span></th>
                                        <th><span class="badge rounded-pill bg-purple text-white" style="background:#9b59b6;">Leave</span></th>
                                        <th><span class="badge rounded-pill bg-orange text-white" style="background:#e67e22;">Under Time</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="fw-semibold fs-5">
                                        <td id="presentCount" class="text-success">0</td>
                                        <td id="absentCount" class="text-danger">0</td>
                                        <td id="lateCount" class="text-warning">0</td>
                                        <td id="leaveCount" style="color:#9b59b6;">0</td>
                                        <td id="underTimeCount" style="color:#e67e22;">0</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pie Chart -->
                <div class="col-md-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="card-title">Staff Attendance Distribution</h5>
                            <canvas id="attendanceChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const ctx = document.getElementById("attendanceChart").getContext("2d");

            // Summary table elements
            const presentCount = document.getElementById("presentCount");
            const absentCount = document.getElementById("absentCount");
            const lateCount = document.getElementById("lateCount");
            const leaveCount = document.getElementById("leaveCount");
            const underTimeCount = document.getElementById("underTimeCount");

            // Pie chart config
            let attendanceChart = new Chart(ctx, {
                type: "pie",
                data: {
                    labels: ["Present", "Absent", "Late", "Leave", "Under Time"],
                    datasets: [{
                        data: [0, 0, 0, 0, 0],
                        backgroundColor: [
                            "#2ecc71",
                            "#e74c3c",
                            "#f39c12",
                            "#9b59b6",
                            "#e67e22"
                        ]
                    }]
                }
            });

            // Extract the date parameter from URL
            const urlParams = new URLSearchParams(window.location.search);
            const selectedDate = urlParams.get('date'); // ?date=YYYY-MM-DD

            // Fetch attendance data for given date
            async function loadAttendance(date) {
                if (!date) return;
                const res = await fetch(`https://localhost:7212/employee/attendanceReport/${date}`);
                const data = await res.json();

                // Update chart
                attendanceChart.data.datasets[0].data = [
                    data.present || 0,
                    data.absent || 0,
                    data.late || 0,
                    data.leave || 0,
                    data.underTime || 0
                ];
                attendanceChart.update();

                // Update summary table
                presentCount.textContent = data.present || 0;
                absentCount.textContent = data.absent || 0;
                lateCount.textContent = data.late || 0;
                leaveCount.textContent = data.leave || 0;
                underTimeCount.textContent = data.underTime || 0;
            }

            // Init
            loadAttendance(selectedDate);
        </script>
    </body>
</div>

</html>