<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Employee Attendance Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- PDF / Excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        body {
            background: #f4f6f9;
        }

        .layout-wrapper {
            display: flex;
            width: 100%;
        }

        #mainArea {
            flex-grow: 1;
            padding: 20px;
            margin-left: 260px;
            /* left sidebar */
        }

        /* Floating AI Button */
        #aiBtn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #f0ad4e;
            color: white;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-weight: bold;
            border: none;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 9999;
        }

        /* Sliding AI Drawer */
        #aiDrawer {
            position: fixed;
            top: 0;
            right: -420px;
            width: 420px;
            height: 100vh;
            background: #fff;
            border-left: 3px solid #f0ad4e;
            padding: 25px;
            overflow-y: auto;
            transition: right .3s ease;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.2);
            z-index: 10000;
        }

        #aiDrawer.open {
            right: 0;
        }

        #aiDrawer h4 {
            background: #f0ad4e;
            color: white;
            padding: 10px;
            border-radius: 10px;
        }

        #closeDrawer {
            float: right;
            cursor: pointer;
            font-size: 20px;
            color: #fff;
        }

        @media(max-width:992px) {
            #aiDrawer {
                width: 100%;
                right: -100%;
            }

            #aiDrawer.open {
                right: 0;
            }

            #mainArea {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>

    <div class="layout-wrapper">

        <!-- LEFT SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN REPORT AREA -->
        <div id="mainArea" class="container-fluid">

            <h2 class="fw-bold mb-4">📊 Employee Attendance Analytics</h2>

            <!-- FILTER -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="fw-semibold">From Month</label>
                        <select id="startMonth" class="form-select">
                            <option value="">Select</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= date("F", mktime(0, 0, 0, $i, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="fw-semibold">From Year</label>
                        <select id="startYear" class="form-select"></select>
                    </div>

                    <div class="col-md-3">
                        <label class="fw-semibold">To Month</label>
                        <select id="endMonth" class="form-select">
                            <option value="">Select</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= date("F", mktime(0, 0, 0, $i, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="fw-semibold">To Year</label>
                        <select id="endYear" class="form-select"></select>
                    </div>

                    <div class="col-12">
                        <button onclick="loadReport()" class="btn btn-primary w-100">Generate Report</button>
                    </div>
                </div>
            </div>

            <!-- SUMMARY -->
            <div class="row mb-4" id="summaryCards"></div>

            <!-- CHARTS -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold">Present vs Absent Trend</h6>
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold">Attendance Breakdown</h6>
                        <canvas id="statusPie"></canvas>
                    </div>
                </div>
            </div>

            <!-- HEATMAP -->
            <div class="card p-3 mb-4 shadow-sm">
                <h5 class="fw-bold">Monthly Attendance Heatmap</h5>
                <div id="heatmap"></div>
            </div>

            <!-- RANKINGS -->
            <div class="card p-3 mb-4 shadow-sm">
                <h5 class="fw-bold">Top & Bottom Performing Months</h5>
                <div id="rankings"></div>
            </div>

            <!-- TABLE -->
            <div class="card mb-4 shadow-sm">
                <div class="table-responsive card-body">
                    <table class="table table-bordered" id="attendanceTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Month</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Leave</th>
                                <th>Undertime</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="text-end" id="exportButtons" style="display:none;">
                <button onclick="exportExcel()" class="btn btn-success me-2">Export Excel</button>
                <button onclick="exportPDF()" class="btn btn-danger">Export PDF</button>
            </div>

        </div>
    </div>

    <!-- AI Drawer Button -->
    <button id="aiBtn">AI</button>

    <!-- AI Drawer -->
    <div id="aiDrawer">
        <h4>AI Insights <span id="closeDrawer" onclick="toggleDrawer()">✖</span></h4>
        <div id="ai_insights" class="mt-3">Run report to load insights...</div>
        <hr>
        <h4>AI Recommendations</h4>
        <div id="ai_reco" class="mt-2"></div>
        <hr>
        <h4>Forecast</h4>
        <div id="ai_forecast" class="mt-2"></div>
    </div>


    <script>
        /* ==== AI DRAWER ==== */
        document.getElementById("aiBtn").onclick = toggleDrawer;

        function toggleDrawer() {
            document.getElementById("aiDrawer").classList.toggle("open");
        }

        /* ==== YEAR POPULATE ==== */
        function populateYears(id) {
            const sel = document.getElementById(id);
            let now = new Date().getFullYear();
            for (let y = 2015; y <= now + 2; y++)
                sel.innerHTML += `<option value="${y}">${y}</option>`;
        }
        populateYears("startYear");
        populateYears("endYear");

        let rawData = [];
        let trendChartInstance;
        let pieChartInstance;

        /* ==== LOAD REPORT ==== */
        async function loadReport() {
            const url = `https://bsis-03.keikaizen.xyz/employee/monthAttendanceRangeQueryReport?start=${startMonth.value}&startYear=${startYear.value}&endMonth=${endMonth.value}&endYear=${endYear.value}`;

            rawData = await fetch(url).then(r => r.json());

            exportButtons.style.display = "block";
            buildSummary();
            buildCharts();
            buildTable();
            buildHeatmap();
            buildRankings();
            buildAI();
        }

        /* ==== SUMMARY ==== */
        function buildSummary() {
            const d = rawData;
            const avg = Math.round((d.present / (d.present + d.absent + d.leave_count)) * 100);

            summaryCards.innerHTML = `
    <div class="col-md-2"><div class="card p-3 text-center shadow"><h4>${d.present}</h4><p>Present</p></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow text-danger"><h4>${d.absent}</h4><p>Absent</p></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow text-warning"><h4>${d.late}</h4><p>Late</p></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow text-info"><h4>${d.leave_count}</h4><p>Leave</p></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow text-secondary"><h4>${d.underTime}</h4><p>Undertime</p></div></div>
    <div class="col-md-2"><div class="card p-3 text-center shadow text-primary"><h4>${avg}%</h4><p>Avg Attendance</p></div></div>`;
        }

        /* ==== CHARTS ==== */
        function buildCharts() {

            const labels = rawData.months.map(m => ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][m.month - 1]);

            /* ---- Trend Line ---- */
            if (trendChartInstance) trendChartInstance.destroy();

            trendChartInstance = new Chart(document.getElementById("trendChart"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                            label: "Present",
                            data: rawData.months.map(m => m.present),
                            borderColor: "green",
                            borderWidth: 3
                        },
                        {
                            label: "Absent",
                            data: rawData.months.map(m => m.absent),
                            borderColor: "red",
                            borderWidth: 3
                        }
                    ]
                }
            });

            /* ---- Pie Chart ---- */
            if (pieChartInstance) pieChartInstance.destroy();

            pieChartInstance = new Chart(document.getElementById("statusPie"), {
                type: "pie",
                data: {
                    labels: ["Present", "Absent", "Late", "Leave", "Undertime"],
                    datasets: [{
                        data: [
                            rawData.present,
                            rawData.absent,
                            rawData.late,
                            rawData.leave_count,
                            rawData.underTime
                        ]
                    }]
                }
            });
        }

        /* ==== TABLE ==== */
        function buildTable() {
            tableBody.innerHTML = rawData.months.map(m => {
                const pct = Math.round((m.present / (m.present + m.absent + m.leave_count)) * 100);
                const name = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][m.month - 1];

                return `
        <tr>
            <td>${name} ${m.year}</td>
            <td>${m.present}</td>
            <td class="text-danger">${m.absent}</td>
            <td class="text-warning">${m.late}</td>
            <td class="text-info">${m.leave_count}</td>
            <td class="text-secondary">${m.underTime}</td>
            <td class="fw-bold">${pct}%</td>
        </tr>`;
            }).join("");
        }

        /* ==== HEATMAP ==== */
        function buildHeatmap() {
            heatmap.innerHTML = rawData.months.map(m => {
                const color = m.absent >= 8 ? "#ffdddd" :
                    m.absent >= 5 ? "#fff3cd" : "#d4edda";

                return `
        <div class="p-2 mb-2" style="background:${color};border-radius:8px;">
            <b>${["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"][m.month-1]} ${m.year}</b>
            — Absent: ${m.absent}, Late: ${m.late}
        </div>`;
            }).join("");
        }

        /* ==== RANKINGS ==== */
        function buildRankings() {
            const best = rawData.months.reduce((a, b) => a.present > b.present ? a : b);
            const worst = rawData.months.reduce((a, b) => a.absent > b.absent ? a : b);

            rankings.innerHTML = `
        <p>🌟 Best Month: <b>${["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"][best.month-1]} ${best.year}</b></p>
        <p>⚠ Worst Month: <b>${["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"][worst.month-1]} ${worst.year}</b></p>
    `;
        }

        /* ==== AI INSIGHTS ==== */
        function buildAI() {
            const m = rawData.months;
            const presentTrend = m[m.length - 1].present - m[0].present;
            const absentTrend = m[m.length - 1].absent - m[0].absent;

            ai_insights.innerHTML = `
        • Attendance trend: ${presentTrend >= 0 ? "<b class='text-success'>Improving</b>" : "<b class='text-danger'>Declining</b>"}<br>
        • Absence trend: ${absentTrend >= 0 ? "<b class='text-danger'>Increasing</b>" : "<b class='text-success'>Decreasing</b>"}<br>
        • Highest late records: <b>${rawData.late}</b><br>
        • Leave cases: <b>${rawData.leave_count}</b><br>
    `;

            ai_reco.innerHTML = `
        • Reward consistent attendance.<br>
        • Coaching for repeatedly late staff.<br>
        • Investigate root causes of absences.<br>
        • Offer flexible schedules during high-leave months.<br>
    `;

            ai_forecast.innerHTML = `
        Predicted Present Next Month: <b>${Math.round(rawData.present * 1.03)}</b><br>
        Expected Absences: <b>${Math.round(rawData.absent*0.9)} - ${Math.round(rawData.absent*1.1)}</b>
    `;
        }

        /* ==== EXPORT EXCEL ==== */
        function exportExcel() {
            const wb = XLSX.utils.table_to_book(attendanceTable, {
                sheet: "Attendance Report"
            });
            XLSX.writeFile(wb, "Attendance_Report.xlsx");
        }

        /* ==== EXPORT PDF ==== */
        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();
            doc.text("Attendance Report", 14, 15);
            doc.autoTable({
                html: "#attendanceTable",
                startY: 20
            });
            doc.save("Attendance_Report.pdf");
        }
    </script>

</body>

</html>