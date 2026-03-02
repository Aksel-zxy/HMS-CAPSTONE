<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Staff Performance & Attendance — Report & Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Export PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body {
            background: #f5f7fa;
            font-family: "Inter", sans-serif;
        }

        /* RIGHT SIDEBAR */
        #rightSidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 340px;
            height: 100vh;
            overflow-y: auto;
            background: #ffffff;
            border-left: 1px solid #dce3eb;
            padding: 20px;
            z-index: 200;
        }

        #rightSidebar h5 {
            font-weight: 700;
            font-size: 1rem;
            color: #0d6efd;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 6px;
            margin-bottom: 15px;
        }

        #rightSidebar section {
            margin-bottom: 28px;
        }

        /* MAIN CONTENT OFFSET */
        .page-container {
            margin-left: 270px;
            margin-right: 360px;
            padding: 30px 25px;
        }

        /* CARDS */
        .report-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 16px;
            border: 1px solid #e3e7ee;
        }

        .report-card h6 {
            font-size: .9rem;
            font-weight: 600;
            color: #6c7a8a;
        }

        .report-card h3 {
            font-size: 1.4rem;
            font-weight: 700;
        }

        /* TABLE */
        table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }

        /* HEATMAP COLORS */
        .heatmap-item {
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        /* FLOATING AI BUTTON */
        #aiFloatingBtn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            font-size: 28px;
        }

        /* SLIDE-IN AI PANEL */
        #aiSlidePanel {
            position: fixed;
            top: 0;
            right: -360px;
            width: 360px;
            height: 100vh;
            background: #ffffff;
            border-left: 1px solid #dce3eb;
            z-index: 9998;
            padding: 20px;
            overflow-y: auto;
            transition: right 0.35s ease-in-out;
        }

        #aiSlidePanel.active {
            right: 0px;
        }

        #closeAISide {
            font-size: 22px;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 12px;
        }

        @media(max-width: 992px) {
            #rightSidebar {
                position: static;
                width: 100%;
                height: auto;
                border-left: none;
                border-top: 1px solid #dce3eb;
                margin-top: 20px;
            }

            .page-container {
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="page-container" id="reportArea">

            <h2 class="fw-bold mb-4">📊 Staff Performance & Attendance Report</h2>

            <!-- FILTERS -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label>Start Date</label>
                        <input type="date" id="start_date" class="form-control" value="2026-02-01">
                    </div>

                    <div class="col-md-4">
                        <label>End Date</label>
                        <input type="date" id="end_date" class="form-control" value="2026-02-22">
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="loadReport()">Load Report</button>
                    </div>
                </div>
            </div>

            <!-- EXPORT BUTTONS -->
            <div class="mb-4 d-flex gap-3">
                <button class="btn btn-success" onclick="exportPDF()">Export PDF</button>
                <button class="btn btn-secondary" onclick="exportCSV()">Export Excel</button>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row mb-4" id="summaryCards"></div>

            <!-- CHARTS -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="report-card">
                        <h6 class="text-center fw-bold">Avg Evaluation Score</h6>
                        <canvas id="avgChart"></canvas>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="report-card">
                        <h6 class="text-center fw-bold">Attendance Breakdown</h6>
                        <canvas id="attendancePie"></canvas>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="report-card">
                        <h6 class="text-center fw-bold">Total Evaluation Score</h6>
                        <canvas id="totalEvalChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TOP RANKING -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header fw-bold">Top Performing Departments</div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Department</th>
                            <th>Avg Score</th>
                            <th>Total Score</th>
                        </tr>
                    </thead>
                    <tbody id="rankingTable"></tbody>
                </table>
            </div>

            <!-- FULL SUMMARY TABLE -->
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold">Department Summary</div>
                <div class="table-responsive p-2">
                    <table class="table table-bordered">
                        <thead class="table-primary">
                            <tr>
                                <th>Department</th>
                                <th>Avg Score</th>
                                <th>Total Score</th>
                                <th>Present</th>
                                <th>Late</th>
                                <th>Undertime</th>
                                <th>Off Duty</th>
                                <th>Overtime</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- FLOATING INSIGHT BUTTON -->
    <button id="aiFloatingBtn" onclick="toggleAI()">💡</button>

    <!-- SLIDE-IN PANEL -->
    <div id="aiSlidePanel">
        <span id="closeAISide" onclick="toggleAI()">✖</span>

        <h5 class="fw-bold mb-3">AI Insights</h5>
        <div id="ai_insights"></div>

        <hr>

        <h5 class="fw-bold mb-3">AI Recommendations</h5>
        <div id="ai_reco"></div>

        <hr>

        <h5 class="fw-bold mb-3">Attendance Prediction</h5>
        <div id="ai_predict"></div>

        <hr>

        <h5 class="fw-bold mb-3">Department Heatmap</h5>
        <div id="heatmap"></div>
    </div>

    <script>
        /* SLIDE PANEL TOGGLE */
        function toggleAI() {
            document.getElementById("aiSlidePanel").classList.toggle("active");
        }

        let rawData = [];

        async function loadReport() {
            const s = document.getElementById("start_date").value;
            const e = document.getElementById("end_date").value;

            const url = `https://bsis-03.keikaizen.xyz/employee/staffPerformanceAndAttendanceReport?start=${s}&end=${e}`;
            const res = await fetch(url);
            rawData = await res.json();

            renderSummary(rawData);
            renderTable(rawData);
            renderCharts(rawData);
            renderRanking(rawData);
            renderHeatmap(rawData);
            generateAI(rawData);
        }

        /* SUMMARY CARDS */
        function renderSummary(data) {
            let avgScore =
                (data.reduce((a, b) => a + b.departmentEvaluationAverageScore, 0) / data.length).toFixed(2);

            let totalLate = data.reduce((a, b) => a + b.deparmentTotalLateEmployee, 0);
            let totalPresent = data.reduce((a, b) => a + b.deparmentTotalPresentEmployee, 0);

            document.getElementById("summaryCards").innerHTML = `
        <div class="col-md-3"><div class="report-card"><h6>Departments</h6><h3>${data.length}</h3></div></div>
        <div class="col-md-3"><div class="report-card"><h6>Hospital Avg Score</h6><h3 class="text-primary">${avgScore}</h3></div></div>
        <div class="col-md-3"><div class="report-card"><h6>Total Present</h6><h3>${totalPresent}</h3></div></div>
        <div class="col-md-3"><div class="report-card"><h6>Total Late</h6><h3 class="text-danger">${totalLate}</h3></div></div>
    `;
        }

        /* TABLE */
        function renderTable(data) {
            document.getElementById("tableBody").innerHTML =
                data
                .map(
                    d => `
        <tr>
            <td>${d.department}</td>
            <td>${d.departmentEvaluationAverageScore.toFixed(2)}</td>
            <td>${d.departmentEvaluationTotalScore}</td>
            <td>${d.deparmentTotalPresentEmployee}</td>
            <td>${d.deparmentTotalLateEmployee}</td>
            <td>${d.deparmentTotalUndertimeEmployee}</td>
            <td>${d.departmentTotalOffDuty}</td>
            <td>${d.departmentTotalOvertime}</td>
        </tr>`
                )
                .join("");
        }

        /* RANKING */
        function renderRanking(data) {
            let sorted = [...data].sort(
                (a, b) => b.departmentEvaluationAverageScore - a.departmentEvaluationAverageScore
            );

            document.getElementById("rankingTable").innerHTML = sorted
                .map(
                    (d, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${d.department}</td>
            <td>${d.departmentEvaluationAverageScore.toFixed(2)}</td>
            <td>${d.departmentEvaluationTotalScore}</td>
        </tr>`
                )
                .join("");
        }

        /* HEATMAP */
        function renderHeatmap(data) {
            let html = "";

            data.forEach(d => {
                const score = d.departmentEvaluationAverageScore;

                const color =
                    score >= 4.5 ? "#c3f7c3" :
                    score >= 4.0 ? "#e3f8cf" :
                    score >= 3.5 ? "#ffeec2" :
                    "#ffd0d0";

                html += `
            <div class="heatmap-item" style="background:${color}">
                <b>${d.department}</b> — ${score.toFixed(2)}
            </div>`;
            });

            document.getElementById("heatmap").innerHTML = html;
        }

        /* CHARTS */
        let chart1, chart2, chart3;

        function renderCharts(data) {
            destroyCharts();

            const dept = data.map(x => x.department);
            const avg = data.map(x => x.departmentEvaluationAverageScore);
            const total = data.map(x => x.departmentEvaluationTotalScore);

            const totalPresent = data.reduce((a, b) => a + b.deparmentTotalPresentEmployee, 0);
            const totalLate = data.reduce((a, b) => a + b.deparmentTotalLateEmployee, 0);
            const totalUnder = data.reduce((a, b) => a + b.deparmentTotalUndertimeEmployee, 0);

            chart1 = new Chart(document.getElementById("avgChart"), {
                type: "bar",
                data: {
                    labels: dept,
                    datasets: [{
                        label: "Avg Score",
                        data: avg
                    }]
                }
            });

            chart2 = new Chart(document.getElementById("attendancePie"), {
                type: "pie",
                data: {
                    labels: ["Present", "Late", "Undertime"],
                    datasets: [{
                        data: [totalPresent, totalLate, totalUnder]
                    }]
                }
            });

            chart3 = new Chart(document.getElementById("totalEvalChart"), {
                type: "line",
                data: {
                    labels: dept,
                    datasets: [{
                        label: "Total Score",
                        data: total
                    }]
                }
            });
        }

        function destroyCharts() {
            if (chart1) chart1.destroy();
            if (chart2) chart2.destroy();
            if (chart3) chart3.destroy();
        }

        /* AI ANALYSIS */
        function generateAI(data) {
            const best = data.reduce((a, b) =>
                a.departmentEvaluationAverageScore > b.departmentEvaluationAverageScore ? a : b
            );

            const worst = data.reduce((a, b) =>
                a.departmentEvaluationAverageScore < b.departmentEvaluationAverageScore ? a : b
            );

            const totalLate = data.reduce((a, b) => a + b.deparmentTotalLateEmployee, 0);

            /* Insights */
            document.getElementById("ai_insights").innerHTML = `
        • Best Department: <b>${best.department}</b> (${best.departmentEvaluationAverageScore.toFixed(2)})<br>
        • Lowest Department: <b>${worst.department}</b> (${worst.departmentEvaluationAverageScore.toFixed(2)})<br>
        • Total Late Occurrences: <b>${totalLate}</b><br>
        • Departments with Overtime: <b>${data.filter(x => x.departmentTotalOvertime > 0).length}</b><br>
        `;

            /* Recommendations */
            document.getElementById("ai_reco").innerHTML = `
        • Improve attendance compliance in <b>${worst.department}</b><br>
        • Enhance interdepartmental collaboration<br>
        • Consider reskilling or coaching programs<br>
        • Review workload distribution to avoid burnout<br>
        `;

            /* Prediction */
            let risk = "Low Attendance Risk";

            if (totalLate > 15) risk = "⚠ High Attendance Risk";
            else if (totalLate > 8) risk = "Moderate Attendance Risk";

            document.getElementById("ai_predict").innerHTML = `
        <b>${risk}</b><br>
        Based on data from ${document.getElementById("start_date").value} to ${document.getElementById("end_date").value}.
        `;
        }

        /* EXPORT */
        function exportPDF() {
            html2pdf().from(document.getElementById("reportArea")).save("Staff_Performance_Report.pdf");
        }

        function exportCSV() {
            let csv = "Department,Avg Score,Total Score,Present,Late,Undertime,Off Duty,Overtime\n";
            rawData.forEach(r => {
                csv += `${r.department},${r.departmentEvaluationAverageScore},${r.departmentEvaluationTotalScore},${r.deparmentTotalPresentEmployee},${r.deparmentTotalLateEmployee},${r.deparmentTotalUndertimeEmployee},${r.departmentTotalOffDuty},${r.departmentTotalOvertime}\n`;
            });

            const link = document.createElement("a");
            link.href = URL.createObjectURL(new Blob([csv], {
                type: "text/csv"
            }));
            link.download = "StaffPerformanceReport.csv";
            link.click();
        }

        loadReport();
    </script>

</body>

</html>