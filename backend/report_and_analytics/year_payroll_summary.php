<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payroll Analytics Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jsPDF + AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <!-- Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        body {
            background: #f5f7fb;
            overflow-x: hidden;
        }

        /* ==========================================================
           FLOATING AI BUTTON
        ========================================================== */
        #aiFloatBtn {
            position: fixed;
            bottom: 28px;
            right: 28px;
            width: 65px;
            height: 65px;
            background: #0d6efd;
            color: white;
            font-size: 26px;
            border-radius: 50%;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            z-index: 9999;
        }

        /* ==========================================================
           AI SLIDE-OUT PANEL
        ========================================================== */
        #aiDrawer {
            position: fixed;
            top: 0;
            right: -420px;
            width: 420px;
            height: 100vh;
            background: #eaf3ff;
            border-left: 6px solid #0d6efd;
            transition: 0.4s ease;
            padding: 20px;
            overflow-y: auto;
            z-index: 99999;
        }

        #aiDrawer.open {
            right: 0;
        }

        #aiDrawer h4 {
            background: linear-gradient(90deg, #0d6efd, #74a8ff);
            color: white;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 18px;
            text-align: center;
        }

        #aiDrawer p {
            font-size: 0.95rem;
            margin-bottom: 12px;
        }

        #aiDrawer b {
            color: #0d6efd;
        }

        #aiDrawer .closeBtn {
            width: 100%;
            background: #0d6efd;
            color: white;
        }

        /* Keep space for left sidebar */
        #contentWrapper {
            margin-left: 260px;
        }

        @media(max-width:992px) {
            #contentWrapper {
                margin-left: 0;
            }
        }
    </style>
</head>

<body class="bg-light">

    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- ==========================================================
     AI FLOAT BUTTON
========================================================== -->
        <button id="aiFloatBtn" onclick="toggleAIDrawer()">🤖</button>

        <!-- ==========================================================
     AI SLIDING DRAWER
========================================================== -->
        <div id="aiDrawer">
            <h4>AI Insights</h4>
            <div id="ai_insights">No data yet...</div>

            <h4>AI Heatmap</h4>
            <div id="ai_heatmap">Waiting for results...</div>

            <h4>AI Recommendations</h4>
            <div id="ai_reco">No suggestions yet...</div>

            <h4>AI Prediction</h4>
            <div id="ai_predict">Not calculated...</div>

            <button class="btn closeBtn mt-4" onclick="toggleAIDrawer()">Close</button>
        </div>

        <!-- ==========================================================
     MAIN CONTENT
========================================================== -->
        <div class="container py-4" id="contentWrapper">

            <h2 class="fw-bold text-primary mb-4">💰 Payroll Analytics Dashboard</h2>

            <!-- FILTER -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Select Month Range</h5>

                    <form id="filterForm" class="row g-3">

                        <div class="col-md-3">
                            <label class="form-label">Start Month</label>
                            <select id="startMonth" class="form-select"></select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Start Year</label>
                            <select id="startYear" class="form-select"></select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">End Month</label>
                            <select id="endMonth" class="form-select"></select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">End Year</label>
                            <select id="endYear" class="form-select"></select>
                        </div>

                        <div class="col-md-12 mt-2">
                            <button class="btn btn-primary w-100">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- KPI -->
            <div class="row g-3 d-none" id="kpiGrid">
                <div class="col-md-3">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiTotalEmployees" class="fw-bold text-primary">0</h4>
                        <p>Total Employees</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiGross" class="fw-bold text-success">₱0</h4>
                        <p>Total Gross Pay</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiDeductions" class="fw-bold text-danger">₱0</h4>
                        <p>Total Deductions</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiNetPay" class="fw-bold text-info">₱0</h4>
                        <p>Total Net Pay</p>
                    </div>
                </div>
            </div>

            <!-- TABLE -->
            <div class="card shadow-sm mt-4 d-none" id="tableCard">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Payroll Breakdown by Month</h5>

                    <table class="table table-bordered text-center" id="payrollTable">
                        <thead class="table-primary">
                            <tr>
                                <th>Month</th>
                                <th>Employees</th>
                                <th>Gross Pay</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                            </tr>
                        </thead>
                        <tbody id="payrollRows"></tbody>
                    </table>
                </div>
            </div>

            <!-- CHARTS -->
            <div class="row mt-4 d-none" id="chartGrid">
                <div class="col-md-6">
                    <div class="card shadow-sm p-3">
                        <h6 class="fw-semibold">Gross vs Net Pay Trend</h6>
                        <canvas id="payChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm p-3">
                        <h6 class="fw-semibold">Deductions & Employee Count</h6>
                        <canvas id="deductionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- EXPORT -->
            <div id="exportButtons" class="d-none text-end mt-3">
                <button onclick="exportExcel()" class="btn btn-success me-2">Excel</button>
                <button onclick="exportPDF()" class="btn btn-danger">PDF</button>
            </div>

        </div>
    </div>

    <!-- ==========================================================
     JAVASCRIPT LOGIC
========================================================== -->
    <script>
        /* ==========================================================
   AI DRAWER TOGGLE
========================================================== */
        function toggleAIDrawer() {
            document.getElementById("aiDrawer").classList.toggle("open");
        }

        /* YEAR + MONTH DROPDOWNS */
        populateYears("startYear");
        populateYears("endYear");
        populateMonths("startMonth");
        populateMonths("endMonth");

        function populateYears(id) {
            const select = document.getElementById(id);
            const now = new Date().getFullYear();
            for (let y = 2020; y <= now + 1; y++) {
                select.innerHTML += `<option value="${y}">${y}</option>`;
            }
        }

        function populateMonths(id) {
            const m = document.getElementById(id);
            m.innerHTML = `<option value="">Select</option>`;
            ["January", "February", "March", "April", "May", "June", "July", "August",
                "September", "October", "November", "December"
            ]
            .forEach((n, i) => m.innerHTML += `<option value="${i+1}">${n}</option>`);
        }

        let payLineChart = null;
        let deductionBarChart = null;
        let rawData = null;

        /* HANDLE FORM SUBMIT */
        document.getElementById("filterForm").addEventListener("submit", async (e) => {
            e.preventDefault();

            const sm = startMonth.value;
            const sy = startYear.value;
            const em = endMonth.value;
            const ey = endYear.value;

            const url = `https://bsis-03.keikaizen.xyz/payroll/monthPayrollRangeQueryAsync?startmonth=${sm}&startyear=${sy}&endmonth=${em}&endyear=${ey}`;

            const res = await fetch(url);
            rawData = await res.json();

            renderPayroll(rawData);
            generateAI(rawData);
        });

        /* ==========================================================
           RENDER PAYROLL DASHBOARD
        ========================================================== */
        function renderPayroll(data) {
            kpiGrid.classList.remove("d-none");
            tableCard.classList.remove("d-none");
            chartGrid.classList.remove("d-none");
            exportButtons.classList.remove("d-none");

            kpiTotalEmployees.innerText = data.total_employees;
            kpiGross.innerText = "₱" + data.total_gross_pay.toLocaleString();
            kpiDeductions.innerText = "₱" + data.total_deductions.toLocaleString();
            kpiNetPay.innerText = "₱" + data.total_net_pay.toLocaleString();

            let rows = "";
            const labels = [];

            data.months.forEach(m => {
                const name = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug",
                    "Sep", "Oct", "Nov", "Dec"
                ][m.month - 1];

                labels.push(name);

                rows += `
        <tr>
            <td>${name} ${m.year}</td>
            <td>${m.total_employees}</td>
            <td>₱${m.total_gross_pay.toLocaleString()}</td>
            <td>₱${m.total_deductions.toLocaleString()}</td>
            <td>₱${m.total_net_pay.toLocaleString()}</td>
        </tr>`;
            });

            payrollRows.innerHTML = rows;

            renderCharts(labels, data.months);
        }

        /* ==========================================================
           CHARTS
        ========================================================== */
        function renderCharts(labels, months) {
            if (payLineChart) payLineChart.destroy();
            if (deductionBarChart) deductionBarChart.destroy();

            payLineChart = new Chart(document.getElementById("payChart"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                            label: "Gross Pay",
                            data: months.map(m => m.total_gross_pay),
                            borderColor: "#0d6efd",
                            borderWidth: 3
                        },
                        {
                            label: "Net Pay",
                            data: months.map(m => m.total_net_pay),
                            borderColor: "#198754",
                            borderWidth: 3
                        }
                    ]
                }
            });

            deductionBarChart = new Chart(document.getElementById("deductionChart"), {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                            label: "Deductions",
                            data: months.map(m => m.total_deductions),
                            backgroundColor: "#dc3545"
                        },
                        {
                            label: "Employees",
                            data: months.map(m => m.total_employees),
                            backgroundColor: "#6c757d"
                        }
                    ]
                }
            });
        }

        /* ==========================================================
           AI ENGINE
        ========================================================== */
        function generateAI(data) {
            const months = data.months;

            const highestGross = months.reduce((a, b) => a.total_gross_pay > b.total_gross_pay ? a : b);
            const lowestNet = months.reduce((a, b) => a.total_net_pay < b.total_net_pay ? a : b);

            ai_insights.innerHTML = `
        ✔ Highest gross payroll: <b>${highestGross.month}/${highestGross.year}</b><br>
        ✔ Lowest net pay: <b>${lowestNet.month}/${lowestNet.year}</b><br>
        ✔ Total net payroll: <b>₱${data.total_net_pay.toLocaleString()}</b><br>
    `;

            /* Heatmap Risk Levels */
            let heatHTML = "";
            months.forEach(m => {
                const risk =
                    m.total_deductions > m.total_gross_pay * 0.15 ? "High" :
                    m.total_deductions > m.total_gross_pay * 0.10 ? "Medium" : "Low";

                const color =
                    risk === "High" ? "#ff6b6b" :
                    risk === "Medium" ? "#ffd166" :
                    "#8fd19e";

                heatHTML += `
            <div style="padding:8px;border-radius:6px;margin-bottom:8px;background:${color}">
                ${m.month}/${m.year} — <b>${risk} Risk</b>
            </div>
        `;
            });
            ai_heatmap.innerHTML = heatHTML;

            /* Recommendations */
            const avgDedRate = Math.round((data.total_deductions / data.total_gross_pay) * 100);

            ai_reco.innerHTML = `
        ${avgDedRate>12?
            `⚠ High deduction ratio detected: <b>${avgDedRate}%</b>. Review tax/benefit load.<br>`:
            `✔ Healthy deduction ratio: <b>${avgDedRate}%</b>.<br>`
        }
        • Review overtime & allowance distribution.<br>
        • Ensure consistent pay structure across months.<br>
    `;

            /* Prediction */
            const diff = months[months.length - 1].total_net_pay - months[0].total_net_pay;
            const trend = diff > 0 ? "increasing" : "decreasing";
            const next = months[months.length - 1].total_net_pay + (diff / months.length);

            ai_predict.innerHTML = `
        📈 Net payroll is <b>${trend}</b>.<br>
        Predicted next month net payroll:<br>
        <b>₱${Math.round(next).toLocaleString()}</b>
    `;
        }

        /* ==========================================================
           EXPORT FUNCTIONS
        ========================================================== */
        function exportExcel() {
            const wb = XLSX.utils.table_to_book(document.getElementById("payrollTable"));
            XLSX.writeFile(wb, "Payroll_Report.xlsx");
        }

        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();
            doc.text("Payroll Report", 14, 15);
            doc.autoTable({
                html: "#payrollTable",
                startY: 20
            });
            doc.save("Payroll_Report.pdf");
        }
    </script>

</body>

</html>