<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Hospital Billing Analytics Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        body {
            background: #f4f6f9;
        }

        /* FLOATING AI BUTTON */
        #aiFloatBtn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #0d6efd;
            color: white;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            cursor: pointer;
            z-index: 9999;
        }

        /* SLIDING AI PANEL (Right Drawer) */
        #aiDrawer {
            position: fixed;
            top: 0;
            right: -380px;
            width: 360px;
            height: 100vh;
            background: #ffffff;
            border-left: 6px solid #0d6efd;
            box-shadow: -4px 0 12px rgba(0, 0, 0, 0.15);
            padding: 20px;
            transition: 0.35s ease;
            z-index: 9998;
            overflow-y: auto;
        }

        #aiDrawer.open {
            right: 0;
        }

        #aiDrawer h4 {
            background: linear-gradient(90deg, #0d6efd, #74a8ff);
            padding: 10px;
            color: white;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
        }

        .chart-box {
            padding: 10px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .1);
        }

        .arrow-up {
            color: green;
            font-weight: bold;
        }

        .arrow-down {
            color: red;
            font-weight: bold;
        }

        .arrow-flat {
            color: gray;
            font-weight: bold;
        }
    </style>

</head>

<body class="bg-light">

    <!-- FLOATING AI BUTTON -->
    <div id="aiFloatBtn" onclick="toggleAIDrawer()">🤖</div>

    <!-- AI SLIDING DRAWER -->
    <div id="aiDrawer">
        <h4>AI Insights</h4>
        <div id="ai_insights"></div>
        <hr>

        <h4>AI Recommendations</h4>
        <div id="ai_reco"></div>
        <hr>

        <h4>AI Forecasting</h4>
        <div id="ai_forecast"></div>
        <hr>

        <h4>AI Billing Risk Map</h4>
        <div id="ai_heatmap"></div>
    </div>

    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <h2 class="fw-bold text-primary mb-4">💳 Hospital Billing Analytics Dashboard</h2>

            <!-- FILTER -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Select Month Range</h5>

                    <form id="filterForm" class="row g-3">

                        <div class="col-md-3">
                            <label class="form-label">From Month</label>
                            <select id="startMonth" class="form-select" required>
                                <option value="">Select</option>
                                <?php
                                $months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                                for ($i = 1; $i <= 12; $i++) echo "<option value='$i'>{$months[$i - 1]}</option>";
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">From Year</label>
                            <select id="startYear" class="form-select">
                                <?php for ($y = 2020; $y <= 2030; $y++) echo "<option>$y</option>"; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">To Month</label>
                            <select id="endMonth" class="form-select" required>
                                <?php
                                for ($i = 1; $i <= 12; $i++) echo "<option value='$i'>{$months[$i - 1]}</option>";
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">To Year</label>
                            <select id="endYear" class="form-select">
                                <?php for ($y = 2020; $y <= 2030; $y++) echo "<option>$y</option>"; ?>
                            </select>
                        </div>

                        <div class="col-md-12 mt-3">
                            <button class="btn btn-primary w-100">Generate Report</button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- KPI SUMMARY -->
            <div class="card shadow-sm mb-4 d-none" id="summaryCard">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Financial Summary Overview</h5>

                    <div class="row text-center">

                        <div class="col-md-2">
                            <h4 class="fw-bold" id="sumBilled">₱0</h4>
                            <div id="arrowBilled"></div>
                            <p>Total Billed</p>
                        </div>

                        <div class="col-md-2">
                            <h4 class="fw-bold" id="sumPaid">₱0</h4>
                            <div id="arrowPaid"></div>
                            <p>Total Paid</p>
                        </div>

                        <div class="col-md-2">
                            <h4 class="fw-bold" id="sumPendingTrans">0</h4>
                            <p>Pending Transactions</p>
                        </div>

                        <div class="col-md-2">
                            <h4 class="fw-bold" id="sumOOP">₱0</h4>
                            <div id="arrowOOP"></div>
                            <p>Out-of-Pocket</p>
                        </div>

                        <div class="col-md-2">
                            <h4 class="fw-bold" id="sumInsurance">₱0</h4>
                            <div id="arrowInsurance"></div>
                            <p>Insurance Covered</p>
                        </div>

                        <div class="col-md-2">
                            <h4 class="fw-bold" id="sumPendingAmt">₱0</h4>
                            <div id="arrowPending"></div>
                            <p>Pending Amount</p>
                        </div>

                    </div>

                    <div class="text-center mt-3">
                        <h4 class="fw-bold text-primary" id="avgBPT">₱0</h4>
                        <p>Average Billing Per Transaction</p>
                    </div>

                </div>
            </div>
            <!-- EXPORT BUTTONS -->
            <div id="exportButtons" class="d-none text-end mb-3">
                <button onclick="exportExcel()" class="btn btn-success me-2">Excel</button>
                <button onclick="exportPDF()" class="btn btn-danger">PDF</button>
            </div>

            <!-- TABLE -->
            <div class="card shadow-sm mb-4 d-none" id="tableCard">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Monthly Billing Breakdown</h5>
                    <table class="table table-bordered text-center" id="billingTable">
                        <thead class="table-primary">
                            <tr>
                                <th>Month</th>
                                <th>Total Billed</th>
                                <th>Total Paid</th>
                                <th>Pending Tx</th>
                                <th>OOP</th>
                                <th>Insurance</th>
                                <th>Pending Amt</th>
                            </tr>
                        </thead>
                        <tbody id="billingRows"></tbody>
                    </table>
                </div>
            </div>

            <!-- CHART GRID -->
            <div class="row d-none" id="chartGrid">

                <div class="col-md-6 mb-3">
                    <div class="chart-box">
                        <h6>Total Billed Trend</h6>
                        <canvas id="chartBilled"></canvas>
                        <button onclick="downloadChart(chartBilledInst,'Billed_Trend')" class="btn btn-outline-primary mt-2 w-100">Download</button>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="chart-box">
                        <h6>Total Paid Trend</h6>
                        <canvas id="chartPaid"></canvas>
                        <button onclick="downloadChart(chartPaidInst,'Paid_Trend')" class="btn btn-outline-primary mt-2 w-100">Download</button>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="chart-box">
                        <h6>OOP vs Insurance</h6>
                        <canvas id="chartOOP"></canvas>
                        <button onclick="downloadChart(chartOOPInst,'OOP_Insurance')" class="btn btn-outline-primary mt-2 w-100">Download</button>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="chart-box">
                        <h6>Pending Amount Trend</h6>
                        <canvas id="chartPending"></canvas>
                        <button onclick="downloadChart(chartPendingInst,'Pending_Trend')" class="btn btn-outline-primary mt-2 w-100">Download</button>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script>
        /* OPEN / CLOSE AI DRAWER */
        function toggleAIDrawer() {
            document.getElementById("aiDrawer").classList.toggle("open");
        }

        /* TREND ARROW LOGIC */
        function arrow(prev, curr) {
            if (curr > prev) return "<span class='arrow-up'>↑</span>";
            if (curr < prev) return "<span class='arrow-down'>↓</span>";
            return "<span class='arrow-flat'>→</span>";
        }

        /* CHART INSTANCES */
        let chartBilledInst, chartPaidInst, chartOOPInst, chartPendingInst;

        /* MAIN SUBMIT */
        document.getElementById("filterForm").addEventListener("submit", async (e) => {
            e.preventDefault();

            const url = `https://bsis-03.keikaizen.xyz/journal/monthBillingRangeReport?start=${startMonth.value}&startYear=${startYear.value}&endMonth=${endMonth.value}&endYear=${endYear.value}`;
            const res = await fetch(url);
            const data = await res.json();

            summaryCard.classList.remove("d-none");
            exportButtons.classList.remove("d-none");
            tableCard.classList.remove("d-none");
            chartGrid.classList.remove("d-none");

            /* SUMMARY */
            sumBilled.innerText = "₱" + data.total_billed.toLocaleString();
            sumPaid.innerText = "₱" + data.total_paid.toLocaleString();
            sumPendingTrans.innerText = data.total_pending_transaction;
            sumOOP.innerText = "₱" + data.total_oop_collected.toLocaleString();
            sumInsurance.innerText = "₱" + data.total_insurance_covered.toLocaleString();
            sumPendingAmt.innerText = "₱" + data.total_pending_amount.toLocaleString();

            let avgB = data.total_pending_transaction == 0 ? 0 :
                data.total_billed / data.total_pending_transaction;
            avgBPT.innerText = "₱" + avgB.toFixed(2);

            /* TABLE */
            let rows = "";
            const names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            data.months.forEach(m => {
                rows += `
                <tr>
                    <td>${names[m.month-1]} ${m.year}</td>
                    <td>₱${m.total_billed.toLocaleString()}</td>
                    <td>₱${m.total_paid.toLocaleString()}</td>
                    <td>${m.total_pending_transaction}</td>
                    <td>₱${m.total_oop_collected.toLocaleString()}</td>
                    <td>₱${m.total_insurance_covered.toLocaleString()}</td>
                    <td>₱${m.total_pending_amount.toLocaleString()}</td>
                </tr>`;
            });
            billingRows.innerHTML = rows;

            /* ARROWS */
            if (data.months.length > 1) {
                let last = data.months[data.months.length - 2];
                let cur = data.months[data.months.length - 1];

                arrowBilled.innerHTML = arrow(last.total_billed, cur.total_billed);
                arrowPaid.innerHTML = arrow(last.total_paid, cur.total_paid);
                arrowOOP.innerHTML = arrow(last.total_oop_collected, cur.total_oop_collected);
                arrowInsurance.innerHTML = arrow(last.total_insurance_covered, cur.total_insurance_covered);
                arrowPending.innerHTML = arrow(last.total_pending_amount, cur.total_pending_amount);
            }

            /* CHART RENDER */
            renderCharts(data);
            renderAI(data);
        });

        /* RENDER CHARTS */
        function renderCharts(data) {
            const names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            let labels = data.months.map(m => names[m.month - 1]);
            let billed = data.months.map(m => m.total_billed);
            let paid = data.months.map(m => m.total_paid);
            let oop = data.months.map(m => m.total_oop_collected);
            let ins = data.months.map(m => m.total_insurance_covered);
            let pending = data.months.map(m => m.total_pending_amount);

            if (chartBilledInst) chartBilledInst.destroy();
            chartBilledInst = new Chart(chartBilled.getContext("2d"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        label: "Total Billed",
                        data: billed,
                        borderColor: "blue",
                        borderWidth: 2
                    }]
                }
            });

            if (chartPaidInst) chartPaidInst.destroy();
            chartPaidInst = new Chart(chartPaid.getContext("2d"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        label: "Total Paid",
                        data: paid,
                        borderColor: "green",
                        borderWidth: 2
                    }]
                }
            });

            if (chartOOPInst) chartOOPInst.destroy();
            chartOOPInst = new Chart(chartOOP.getContext("2d"), {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                            label: "OOP",
                            data: oop,
                            backgroundColor: "orange"
                        },
                        {
                            label: "Insurance",
                            data: ins,
                            backgroundColor: "purple"
                        }
                    ]
                }
            });

            if (chartPendingInst) chartPendingInst.destroy();
            chartPendingInst = new Chart(chartPending.getContext("2d"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        label: "Pending",
                        data: pending,
                        borderColor: "red",
                        borderWidth: 2
                    }]
                }
            });
        }

        /* AI ENGINE */
        function renderAI(data) {
            const months = data.months;
            const names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

            let last = months.length > 1 ? months[months.length - 2] : months[0];
            let cur = months[months.length - 1];

            /* INSIGHTS */
            let insights = "";
            if (cur.total_billed > last.total_billed * 1.25) insights += "<p>🔥 Large spike in billing activity.</p>";
            if (cur.total_paid < last.total_paid * 0.75) insights += "<p>⚠ Payments dropped significantly.</p>";
            if (cur.total_pending_amount > last.total_pending_amount * 1.30) insights += "<p>🚨 Pending amount rising too fast!</p>";

            let maxBill = months.reduce((a, b) => a.total_billed > b.total_billed ? a : b);
            let maxPend = months.reduce((a, b) => a.total_pending_amount > b.total_pending_amount ? a : b);

            insights += `
                <p>✔ Highest Billing: <b>${names[maxBill.month-1]} ${maxBill.year}</b></p>
                <p>✔ Highest Pending Amount: <b>${names[maxPend.month-1]} ${maxPend.year}</b></p>
            `;
            ai_insights.innerHTML = insights;

            /* AI RECOMMENDATIONS */
            let rec = "";
            if (cur.total_pending_amount > last.total_pending_amount)
                rec += "<p>✔ Increase follow-ups on unpaid balances.</p>";
            if (cur.total_paid < last.total_paid)
                rec += "<p>✔ Collections strategy may need adjustment.</p>";
            if (cur.total_insurance_covered > last.total_insurance_covered)
                rec += "<p>✔ Insurance claims processing improved.</p>";
            ai_reco.innerHTML = rec || "<p>No major actions detected.</p>";

            /* FORECASTING: 3-month linear prediction */
            let billed = months.map(m => m.total_billed);
            let forecast = predict3(billed);

            ai_forecast.innerHTML = `
                <p>📈 Next 3-month Billed Forecast:</p>
                <p><b>₱${forecast[0].toLocaleString()}</b>,
                <b>₱${forecast[1].toLocaleString()}</b>,
                <b>₱${forecast[2].toLocaleString()}</b></p>
            `;

            /* RISK HEATMAP */
            let heat = "";
            months.forEach(m => {
                let risk = m.total_pending_amount > m.total_billed * 0.30 ? "High" :
                    m.total_pending_amount > m.total_billed * 0.15 ? "Medium" :
                    "Low";

                let color = risk === "High" ? "#ff7b7b" :
                    risk === "Medium" ? "#ffd166" :
                    "#8ae48a";

                heat += `
                    <div style="padding:8px;border-radius:6px;margin-bottom:6px;background:${color}">
                        ${names[m.month-1]} ${m.year} → <b>${risk}</b> risk
                    </div>
                `;
            });
            ai_heatmap.innerHTML = heat;
        }

        /* LINEAR FORECAST FUNCTION */
        function predict3(vals) {
            let n = vals.length;
            let x = vals.map((_, i) => i + 1);
            let sumX = x.reduce((a, b) => a + b);
            let sumY = vals.reduce((a, b) => a + b);
            let sumXY = x.reduce((a, b, i) => a + b * vals[i], 0);
            let sumX2 = x.reduce((a, b) => a + b * b, 0);

            let slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
            let intercept = (sumY - slope * sumX) / n;

            return [
                Math.round(intercept + slope * (n + 1)),
                Math.round(intercept + slope * (n + 2)),
                Math.round(intercept + slope * (n + 3))
            ];
        }

        /* EXPORTS */
        function exportExcel() {
            let wb = XLSX.utils.table_to_book(document.getElementById("billingTable"));
            XLSX.writeFile(wb, "Billing_Report.xlsx");
        }

        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            let doc = new jsPDF();
            doc.text("Billing Report", 14, 15);
            doc.autoTable({
                html: "#billingTable",
                startY: 20
            });
            doc.save("Billing_Report.pdf");
        }

        function downloadChart(chart, name) {
            let a = document.createElement("a");
            a.href = chart.toBase64Image();
            a.download = name + ".png";
            a.click();
        }
    </script>
</body>

</html>