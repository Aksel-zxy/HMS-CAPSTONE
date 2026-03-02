<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Pharmacy Sales Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CHART JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- PDF EXPORT -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <!-- EXCEL EXPORT -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        body {
            background: #f4f6f9;
            overflow-x: hidden;
        }

        .chart-box {
            padding: 15px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .1);
        }

        .modal-chart {
            height: 300px !important;
        }

        /* ================================================================
            FLOATING AI BUTTON
        ================================================================ */
        #aiFloatBtn {
            position: fixed;
            bottom: 28px;
            right: 28px;
            width: 65px;
            height: 65px;
            background: #ff7f27;
            color: white;
            font-size: 28px;
            border-radius: 50%;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            z-index: 9999;
        }

        /* ================================================================
            RIGHT SLIDING AI DRAWER
        ================================================================ */
        #aiDrawer {
            position: fixed;
            top: 0;
            right: -420px;
            width: 420px;
            height: 100vh;
            background: #fff7e6;
            border-left: 7px solid #ff7f27;
            padding: 20px;
            overflow-y: auto;
            transition: 0.35s ease-in-out;
            z-index: 99999;
        }

        #aiDrawer.open {
            right: 0;
        }

        #aiDrawer h4 {
            background: linear-gradient(90deg, #ff7f27, #ffae63);
            color: white;
            padding: 10px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }

        #aiDrawer .closeBtn {
            background: #ff7f27;
            color: white;
            width: 100%;
        }

        #aiDrawer p {
            font-size: 0.92rem;
            margin-bottom: 10px;
        }

        #aiDrawer b {
            color: #ff6a00;
        }
    </style>
</head>

<body>

    <div class="d-flex">

        <?php include 'sidebar.php'; ?>

        <!-- ============================================================
             FLOATING AI ACTION BUTTON
        ============================================================ -->
        <button id="aiFloatBtn" onclick="toggleDrawer()">🤖</button>

        <!-- ============================================================
             AI SLIDING DRAWER
        ============================================================ -->
        <div id="aiDrawer">

            <h4>AI Insights</h4>
            <div id="aiInsights">Run report to generate insights...</div>

            <h4>AI Recommendations</h4>
            <div id="aiReco">...</div>

            <h4>AI Forecast</h4>
            <div id="aiForecast">...</div>

            <h4>AI Heatmap</h4>
            <div id="aiHeatmap">...</div>

            <button class="btn closeBtn mt-3" onclick="toggleDrawer()">Close</button>
        </div>

        <!-- ============================================================
             MAIN REPORT BODY
        ============================================================ -->
        <div class="container py-4">

            <h2 class="fw-bold text-primary mb-4">🧪 Pharmacy Sales Analytics</h2>

            <!-- FILTER -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Select Month Range</h5>

                    <form id="filterForm" class="row g-3">

                        <div class="col-md-3">
                            <label class="form-label">Start Month</label>
                            <select id="startMonth" class="form-select" required>
                                <option value="">Select</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>"><?= date("F", mktime(0, 0, 0, $i, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Start Year</label>
                            <select id="startYear" class="form-select"></select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">End Month</label>
                            <select id="endMonth" class="form-select" required>
                                <option value="">Select</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>"><?= date("F", mktime(0, 0, 0, $i, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">End Year</label>
                            <select id="endYear" class="form-select"></select>
                        </div>

                        <div class="col-md-12 mt-2">
                            <button class="btn btn-primary w-100">Generate Sales Report</button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- KPI GRID -->
            <div class="row g-3 d-none" id="kpiGrid">

                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiTotalTransactions" class="fw-bold text-primary">0</h4>
                        <p>Total Transactions</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiTotalSales" class="fw-bold text-success">₱0</h4>
                        <p>Total Sales</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiTopItem" class="fw-bold text-danger">-</h4>
                        <p>Top Selling Item</p>
                    </div>
                </div>

                <!-- AVG KPIs -->
                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiAvgSales" class="fw-bold text-primary">₱0</h4>
                        <p>Avg Monthly Sales</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiAvgTransactions" class="fw-bold text-success">0</h4>
                        <p>Avg Monthly Transactions</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiAvgSPT" class="fw-bold text-warning">₱0</h4>
                        <p>Avg Sales per Transaction</p>
                    </div>
                </div>

            </div>

            <!-- SALES TABLE -->
            <div class="card shadow-sm mt-4 d-none" id="tableCard">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Monthly Breakdown</h5>
                    <table class="table table-bordered text-center" id="salesTable">
                        <thead class="table-primary">
                            <tr>
                                <th>Month</th>
                                <th>Total Sales</th>
                                <th>Transactions</th>
                                <th>Top Item</th>
                            </tr>
                        </thead>
                        <tbody id="salesRows"></tbody>
                    </table>
                </div>
            </div>

            <!-- 4 CHARTS -->
            <div class="row mt-4 d-none" id="chartGrid">

                <div class="col-md-6">
                    <div class="chart-box">
                        <h6 class="fw-semibold">Sales Trend</h6>
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="chart-box">
                        <h6 class="fw-semibold">Transactions Trend</h6>
                        <canvas id="transactionChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6 mt-3">
                    <div class="chart-box">
                        <h6 class="fw-semibold">Sales Contribution</h6>
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6 mt-3">
                    <div class="chart-box">
                        <h6 class="fw-semibold">Average Sales Per Transaction</h6>
                        <canvas id="avgChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- INSIGHTS -->
            <div class="card shadow-sm mt-4 d-none" id="insightsCard">
                <div class="card-body">
                    <h5 class="fw-bold">📘 Insights & Forecasting</h5>
                    <div id="insightsContent"></div>
                </div>
            </div>

            <!-- EXPORT -->
            <div id="exportButtons" class="d-none text-end mt-3">
                <button onclick="exportExcel()" class="btn btn-success me-2">Excel</button>
                <button onclick="exportPDF()" class="btn btn-danger">PDF</button>
            </div>

            <!-- DAILY MODAL -->
            <div class="modal fade" id="dailyModal">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Daily Sales Breakdown</h5>
                        </div>
                        <div class="modal-body"><canvas id="dailyChart" class="modal-chart"></canvas></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        /* ============================================================
    TOGGLE SLIDING AI DRAWER
============================================================ */
        function toggleDrawer() {
            document.getElementById('aiDrawer').classList.toggle('open');
        }

        /* ============================================================
            YEAR DROPDOWNS
        ============================================================ */
        function populateYears(id) {
            let s = document.getElementById(id);
            let now = new Date().getFullYear();
            for (let y = 2015; y <= now + 2; y++)
                s.innerHTML += `<option value="${y}">${y}</option>`;
        }
        populateYears("startYear");
        populateYears("endYear");

        /* ============================================================
            FORECAST FUNCTION
        ============================================================ */
        function forecast(vals) {
            let n = vals.length;
            let x = vals.map((_, i) => i + 1);

            let sumX = x.reduce((a, b) => a + b),
                sumY = vals.reduce((a, b) => a + b),
                sumXY = x.reduce((a, b, i) => a + b * vals[i], 0),
                sumX2 = x.reduce((a, b) => a + b * b, 0);

            let slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
            let intercept = (sumY - slope * sumX) / n;

            return [
                Math.round(intercept + slope * (n + 1)),
                Math.round(intercept + slope * (n + 2)),
                Math.round(intercept + slope * (n + 3))
            ];
        }

        /* ============================================================
            CHART INSTANCES
        ============================================================ */
        let salesChartInst, transactionChartInst, pieChartInst, avgChartInst, dailyChartInst;

        filterForm.addEventListener("submit", async e => {
            e.preventDefault();

            let url = `https://bsis-03.keikaizen.xyz/journal/monthPharmacyRangeReport?start=${startMonth.value}&startYear=${startYear.value}&endMonth=${endMonth.value}&endYear=${endYear.value}`;

            let r = await fetch(url);
            let data = await r.json();

            render(data);
        });

        /* ============================================================
            MAIN RENDER FUNCTION
        ============================================================ */
        function render(data) {

            kpiGrid.classList.remove("d-none");
            tableCard.classList.remove("d-none");
            chartGrid.classList.remove("d-none");
            insightsCard.classList.remove("d-none");
            exportButtons.classList.remove("d-none");

            /* KPIs */
            kpiTotalTransactions.innerText = data.totalTransactions;
            kpiTotalSales.innerText = "₱" + data.totalSales.toLocaleString();
            kpiTopItem.innerText = data.topSellingItem;

            /* TABLE + ARRAYS */
            let labels = [],
                sales = [],
                trans = [],
                rows = "";

            data.months.forEach(m => {
                let n = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][m.month - 1];

                labels.push(n);
                sales.push(m.totalSales);
                trans.push(m.totalTransactions);

                rows += `
<tr onclick="openDaily(${m.month},${m.year})" style="cursor:pointer">
<td>${n} ${m.year}</td>
<td>₱${m.totalSales.toLocaleString()}</td>
<td>${m.totalTransactions}</td>
<td>${m.topSellingItem}</td>
</tr>`;
            });

            salesRows.innerHTML = rows;

            /* CALCULATE AVERAGES */
            let avgSales = sales.reduce((a, b) => a + b, 0) / sales.length;
            let avgTransactions = trans.reduce((a, b) => a + b, 0) / trans.length;
            let avgSPT = avgSales / avgTransactions;

            kpiAvgSales.innerText = "₱" + Math.round(avgSales).toLocaleString();
            kpiAvgTransactions.innerText = avgTransactions.toFixed(0);
            kpiAvgSPT.innerText = "₱" + avgSPT.toFixed(2);

            /* CHARTS */
            if (salesChartInst) salesChartInst.destroy();
            salesChartInst = new Chart(document.getElementById("salesChart"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        label: "Sales",
                        data: sales,
                        borderColor: "green",
                        borderWidth: 2
                    }]
                }
            });

            if (transactionChartInst) transactionChartInst.destroy();
            transactionChartInst = new Chart(document.getElementById("transactionChart"), {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                        label: "Transactions",
                        data: trans,
                        backgroundColor: "blue"
                    }]
                }
            });

            if (pieChartInst) pieChartInst.destroy();
            pieChartInst = new Chart(document.getElementById("pieChart"), {
                type: "pie",
                data: {
                    labels,
                    datasets: [{
                        data: sales
                    }]
                }
            });

            if (avgChartInst) avgChartInst.destroy();
            avgChartInst = new Chart(document.getElementById("avgChart"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        label: "Avg ₱ per Transaction",
                        data: sales.map((s, i) => s / trans[i]),
                        borderColor: "orange"
                    }]
                }
            });

            /* FORECAST */
            let fSales = forecast(sales);
            let fTrans = forecast(trans);

            /* AI INSIGHTS */
            aiInsights.innerHTML = `
        <p>📈 <b>Next 3-month Sales Forecast:</b><br> ₱${fSales.join(", ₱")}</p>
        <p>📊 <b>Next 3-month Transactions Forecast:</b><br> ${fTrans.join(", ")}</p>
        <p>💊 <b>Strongest Item:</b> ${data.topSellingItem}</p>
        <p>📌 Avg Monthly Sales: <b>₱${Math.round(avgSales).toLocaleString()}</b></p>
    `;

            /* AI RECOMMENDATIONS */
            let rec = "";
            if (fSales[0] > sales[sales.length - 1])
                rec += "<p>✔ Trend rising — Increase inventory for high-movement medicines.</p>";
            else
                rec += "<p>✔ Possible dip — Launch promos or bundles to boost sales.</p>";

            if (avgSPT > 150)
                rec += "<p>✔ Customers spend high — Push premium medications + vitamins.</p>";

            aiReco.innerHTML = rec;

            /* AI FORECAST PANEL */
            aiForecast.innerHTML = `
        <p>Projected Growth: <b>${(fSales[0] - sales[sales.length-1])}</b> (next period)</p>
        <p>Transactions Forecast: <b>${fTrans.join(", ")}</b></p>
    `;

            /* AI HEATMAP (High -> Low Sellers) */
            let maxSale = Math.max(...sales);
            let minSale = Math.min(...sales);

            let heatHTML = "";
            labels.forEach((m, i) => {
                let pct = (sales[i] / maxSale) * 100;
                let color =
                    pct > 75 ? "#ff9f1c" :
                    pct > 50 ? "#ffd166" :
                    pct > 25 ? "#d8f3dc" :
                    "#b7e4c7";

                heatHTML += `
            <div style="background:${color};padding:8px;border-radius:6px;margin-bottom:6px">
                ${m}: ₱${sales[i].toLocaleString()}
            </div>
        `;
            });

            aiHeatmap.innerHTML = heatHTML;
        }

        /* ============================================================
            DAILY DRILL-DOWN
        ============================================================ */
        function openDaily(month, year) {
            let ctx = document.getElementById("dailyChart").getContext("2d");
            let days = [...Array(30).keys()].map(i => i + 1);
            let vals = days.map(() => Math.floor(Math.random() * 5000) + 500);

            if (dailyChartInst) dailyChartInst.destroy();

            dailyChartInst = new Chart(ctx, {
                type: "line",
                data: {
                    labels: days,
                    datasets: [{
                        label: "Daily Sales",
                        data: vals,
                        borderColor: "red"
                    }]
                }
            });

            new bootstrap.Modal(document.getElementById("dailyModal")).show();
        }

        /* ============================================================
            EXPORT FUNCTIONS
        ============================================================ */
        function exportExcel() {
            let wb = XLSX.utils.table_to_book(document.getElementById("salesTable"));
            XLSX.writeFile(wb, "Pharmacy_Sales_Report.xlsx");
        }

        function exportPDF() {
            let doc = new jspdf.jsPDF();
            doc.text("Pharmacy Sales Report", 14, 15);
            doc.autoTable({
                html: "#salesTable",
                startY: 20
            });
            doc.save("Pharmacy_Sales_Report.pdf");
        }
    </script>

</body>

</html>