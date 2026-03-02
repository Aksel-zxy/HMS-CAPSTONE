<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Attendance Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jsPDF & AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <!-- SheetJS (Excel export) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

</head>

<body class="bg-light">
    <div class="d-flex">

        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <?php
            $month = $_GET['month'] ?? 1;
            $year = $_GET['year'] ?? 2025;

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
            $monthLabel = $monthNames[$month - 1];
            ?>

            <h2 class="mb-4 fw-bold text-primary">📅 Attendance Report — <?= $monthLabel . " " . $year ?></h2>

            <!-- ======================= SUMMARY CARDS ======================= -->
            <div class="row g-3 mb-4" id="summaryCards">
                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-success" id="sumPresent">0</h4>
                        <p class="m-0">Present</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-danger" id="sumAbsent">0</h4>
                        <p class="m-0">Absent</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-warning" id="sumLate">0</h4>
                        <p class="m-0">Late</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-info" id="sumLeave">0</h4>
                        <p class="m-0">Leave</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-secondary" id="sumUnderTime">0</h4>
                        <p class="m-0">Undertime</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-primary" id="sumAttendanceRate">0%</h4>
                        <p class="m-0">Attendance Rate</p>
                    </div>
                </div>
            </div>

            <!-- ======================= CHART ======================= -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Attendance Breakdown</h5>
                    <canvas id="attendanceChart" height="120"></canvas>
                </div>
            </div>

            <!-- ======================= AI INSIGHTS ======================= -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">🤖 AI Insights & Recommendations</h5>
                    <div id="insightsContent" class="p-2"></div>
                </div>
            </div>

            <!-- ======================= EXPORT BUTTONS ======================= -->
            <div class="text-end mb-4">
                <button class="btn btn-success me-2" onclick="exportExcel()">Export to Excel</button>
                <button class="btn btn-danger" onclick="exportPDF()">Export to PDF</button>
            </div>

        </div> <!-- container -->
    </div> <!-- d-flex -->

    <script>
        const month = <?= $month ?>;
        const year = <?= $year ?>;

        const url = `https://localhost:7212/employee/getMonthAttendanceReport/${month}/${year}`;

        let chart = null;

        async function loadReport() {

            const response = await fetch(url);
            const data = await response.json();

            // SUMMARY VALUES
            document.getElementById("sumPresent").innerText = data.present;
            document.getElementById("sumAbsent").innerText = data.absent;
            document.getElementById("sumLate").innerText = data.late;
            document.getElementById("sumLeave").innerText = data.leave;
            document.getElementById("sumUnderTime").innerText = data.underTime;

            const attendanceRate = Math.round((data.present / (data.present + data.absent + data.leave)) * 100);
            document.getElementById("sumAttendanceRate").innerText = attendanceRate + "%";

            // CHART DATA
            const ctx = document.getElementById("attendanceChart").getContext("2d");
            if (chart) chart.destroy();

            chart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: ["Present", "Absent", "Late", "Leave", "Undertime"],
                    datasets: [{
                        label: "Attendance Count",
                        data: [
                            data.present,
                            data.absent,
                            data.late,
                            data.leave,
                            data.underTime
                        ],
                        backgroundColor: [
                            "green", "red", "orange", "deepskyblue", "gray"
                        ]
                    }]
                }
            });

            // AI INSIGHTS
            document.getElementById("insightsContent").innerHTML = generateInsights(data, attendanceRate);
        }

        function generateInsights(d, rate) {

            let insights = "";

            insights += `<p>✔ The attendance rate for this month is <b>${rate}%</b>.</p>`;

            if (d.absent > 5) {
                insights += `<p>⚠ High number of absences detected (<b>${d.absent}</b>). Consider reviewing staff scheduling or potential burnout.</p>`;
            } else {
                insights += `<p>✔ Absences remain within an acceptable range.</p>`;
            }

            if (d.late > 5) {
                insights += `<p>⚠ Late arrivals (<b>${d.late}</b>) indicate possible issues with shift timing or transportation.</p>`;
            }

            if (d.leave > 3) {
                insights += `<p>⚠ Higher leave count than usual may suggest increased medical or personal leave usage.</p>`;
            }

            if (d.underTime > 5) {
                insights += `<p>⚠ Undertime (<b>${d.underTime}</b>) suggests workflow inefficiencies or early shift endings.</p>`;
            }

            insights += `
        <h6 class="fw-bold mt-3">Recommendations:</h6>
        <ul>
            <li>Review attendance anomalies and cross-check with HR logs.</li>
            <li>Offer schedule flexibility during high-late months.</li>
            <li>Monitor departments with recurring undertime trends.</li>
            <li>Recognize employees with perfect or near-perfect attendance.</li>
        </ul>
        `;

            return insights;
        }

        // EXPORTS
        function exportExcel() {
            const wb = XLSX.utils.json_to_sheet([{
                Present: document.getElementById("sumPresent").innerText,
                Absent: document.getElementById("sumAbsent").innerText,
                Late: document.getElementById("sumLate").innerText,
                Leave: document.getElementById("sumLeave").innerText,
                Undertime: document.getElementById("sumUnderTime").innerText
            }]);

            const wbFile = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wbFile, wb, "Attendance Report");

            XLSX.writeFile(wbFile, "Monthly_Attendance_Report.xlsx");
        }

        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            doc.text("Monthly Attendance Report", 14, 15);

            doc.autoTable({
                head: [
                    ["Metric", "Value"]
                ],
                body: [
                    ["Present", document.getElementById("sumPresent").innerText],
                    ["Absent", document.getElementById("sumAbsent").innerText],
                    ["Late", document.getElementById("sumLate").innerText],
                    ["Leave", document.getElementById("sumLeave").innerText],
                    ["Undertime", document.getElementById("sumUnderTime").innerText],
                    ["Attendance Rate", document.getElementById("sumAttendanceRate").innerText]
                ],
                startY: 20
            });

            doc.save("Monthly_Attendance_Report.pdf");
        }

        loadReport();
    </script>

</body>

</html>