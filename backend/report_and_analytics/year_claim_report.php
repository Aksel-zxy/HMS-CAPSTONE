<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Insurance Claims Analytics Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- html2pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <!-- Excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        body {
            background: #f4f6f9;
            overflow-x: hidden;
        }

        /* ------------------- FLOATING AI BUTTON ------------------- */
        #aiButton {
            position: fixed;
            bottom: 28px;
            right: 28px;
            background: #ff9800;
            color: white;
            padding: 16px 20px;
            border-radius: 50%;
            font-size: 22px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
            z-index: 9999;
            transition: 0.25s ease;
        }

        #aiButton:hover {
            transform: scale(1.15);
        }

        /* ------------------- SLIDING AI DRAWER ------------------- */
        #aiDrawer {
            position: fixed;
            top: 0;
            right: -380px;
            width: 360px;
            height: 100vh;
            background: #ffffff;
            border-left: 5px solid #ff9800;
            padding: 22px;
            box-shadow: -6px 0 12px rgba(0, 0, 0, 0.18);
            overflow-y: auto;
            transition: right 0.35s ease-in-out;
            z-index: 9998;
        }

        #aiDrawer.open {
            right: 0;
        }

        #aiDrawer h4 {
            font-weight: bold;
            color: #ff9800;
        }

        #closeDrawer {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 22px;
            cursor: pointer;
        }

        /* Keep charts readable */
        canvas {
            max-height: 260px !important;
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- MAIN REPORT AREA -->
        <div class="container py-4" id="reportArea">

            <h2 class="fw-bold mb-3">🧾 Insurance Claims — Report & Analytics</h2>

            <!-- FILTER -->
            <div class="card mb-4">
                <div class="card-body row g-3">
                    <div class="col-md-5">
                        <label>Start Date</label>
                        <input type="date" id="start" class="form-control">
                    </div>

                    <div class="col-md-5">
                        <label>End Date</label>
                        <input type="date" id="end" class="form-control">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="loadReport()">Load</button>
                    </div>
                </div>
            </div>

            <!-- KPI GRID -->
            <div class="row mb-4" id="kpiGrid"></div>

            <!-- CHARTS ROW 1 -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold text-center">Monthly Claim Trend</h6>
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold text-center">Approved vs Denied Amounts</h6>
                        <canvas id="amountChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- PROVIDER CHART -->
            <div class="card p-3 mb-4 shadow-sm">
                <h6 class="fw-bold text-center">Claims per Provider</h6>
                <canvas id="providerChart"></canvas>
            </div>

            <!-- PROVIDER TABLE -->
            <div class="card p-3 mb-4 shadow-sm">
                <h5 class="fw-bold">Provider Breakdown</h5>
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Provider</th>
                            <th>Total Claims</th>
                            <th>Approved</th>
                            <th>Denied</th>
                            <th>Approved Amount</th>
                            <th>Denied Amount</th>
                        </tr>
                    </thead>
                    <tbody id="providerTable"></tbody>
                </table>
            </div>

            <!-- EXPORT BUTTONS -->
            <div class="d-flex gap-3 justify-content-end">
                <button class="btn btn-success" onclick="exportExcel()">Export Excel</button>
                <button class="btn btn-danger" onclick="exportPDF()">Export PDF</button>
            </div>

        </div>
    </div>

    <!-- FLOATING AI BUTTON -->
    <div id="aiButton">🤖</div>

    <!-- AI DRAWER -->
    <div id="aiDrawer">
        <span id="closeDrawer">&times;</span>
        <h4>AI Insights</h4>
        <div id="aiInsights" class="mb-3"></div>

        <h4>Recommendations</h4>
        <div id="aiReco" class="mb-3"></div>

        <h4>Forecast</h4>
        <div id="aiForecast"></div>
    </div>

    <script>
        let rawData = null;
        let chart1, chart2, chart3;

        /* ---------------- AI Drawer Toggle ---------------- */
        document.getElementById("aiButton").onclick = () => {
            document.getElementById("aiDrawer").classList.add("open");
        };
        document.getElementById("closeDrawer").onclick = () => {
            document.getElementById("aiDrawer").classList.remove("open");
        };

        /* ---------------- Load Report ---------------- */
        async function loadReport() {
            const s = document.getElementById("start").value;
            const e = document.getElementById("end").value;

            const url = `https://bsis-03.keikaizen.xyz/insurance/monthInsuranceClaimRangeQuery?start=${s}&end=${e}`;
            const res = await fetch(url);
            rawData = await res.json();

            renderKPIs(rawData);
            renderCharts(rawData);
            renderProviderTable(rawData.providers);
            generateAI(rawData);
        }

        /* ---------------- KPI SUMMARY ---------------- */
        function renderKPIs(d) {
            document.getElementById("kpiGrid").innerHTML = `
                <div class="col-md-3">
                    <div class="p-3 bg-white shadow-sm rounded text-center">
                        <h3>${d.total_claims}</h3>
                        <p>Total Claims</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-success text-white shadow-sm rounded text-center">
                        <h3>${d.total_approved_claims}</h3>
                        <p>Approved</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-danger text-white shadow-sm rounded text-center">
                        <h3>${d.total_denied_claims}</h3>
                        <p>Denied</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-primary text-white shadow-sm rounded text-center">
                        <h3>₱${d.total_amount_approved.toLocaleString()}</h3>
                        <p>Approved Amount</p>
                    </div>
                </div>
            `;
        }

        /* ---------------- Provider Table ---------------- */
        function renderProviderTable(list) {
            document.getElementById("providerTable").innerHTML =
                list.map(p => `
                <tr>
                    <td>${p.provider_name}</td>
                    <td>${p.total_claims}</td>
                    <td>${p.approved_claims}</td>
                    <td>${p.denied_claims}</td>
                    <td>₱${p.approved_amount.toLocaleString()}</td>
                    <td>₱${p.denied_amount.toLocaleString()}</td>
                </tr>`).join("");
        }

        /* ---------------- CHART RENDERING ---------------- */
        function destroyCharts() {
            if (chart1) chart1.destroy();
            if (chart2) chart2.destroy();
            if (chart3) chart3.destroy();
        }

        function renderCharts(d) {
            destroyCharts();

            const months = d.months.map(m => "Month " + m.month);
            const total = d.months.map(m => m.total_claims);
            const approved = d.months.map(m => m.total_approved_claims);
            const denied = d.months.map(m => m.total_denied_claims);

            chart1 = new Chart(document.getElementById("monthlyChart"), {
                type: "line",
                data: {
                    labels: months,
                    datasets: [{
                            label: "Total",
                            data: total,
                            borderWidth: 3
                        },
                        {
                            label: "Approved",
                            data: approved,
                            borderWidth: 3
                        },
                        {
                            label: "Denied",
                            data: denied,
                            borderWidth: 3
                        }
                    ]
                }
            });

            chart2 = new Chart(document.getElementById("amountChart"), {
                type: "bar",
                data: {
                    labels: months,
                    datasets: [{
                            label: "Approved Amount",
                            data: d.months.map(m => m.total_amount_paid)
                        },
                        {
                            label: "Denied Amount",
                            data: d.months.map(m => m.total_amount_denied)
                        }
                    ]
                }
            });

            chart3 = new Chart(document.getElementById("providerChart"), {
                type: "bar",
                data: {
                    labels: d.providers.map(p => p.provider_name),
                    datasets: [{
                        label: "Total Claims",
                        data: d.providers.map(p => p.total_claims)
                    }]
                }
            });
        }

        /* ---------------- AI INSIGHTS & FORECAST ---------------- */
        function generateAI(d) {
            const highestDenied = d.providers.reduce((a, b) =>
                a.denied_amount > b.denied_amount ? a : b
            );

            const mostApproved = d.providers.reduce((a, b) =>
                a.approved_claims > b.approved_claims ? a : b
            );

            document.getElementById("aiInsights").innerHTML = `
                • Highest denial: <b>${highestDenied.provider_name}</b> (₱${highestDenied.denied_amount.toLocaleString()})<br>
                • Most approvals: <b>${mostApproved.provider_name}</b><br>
                • Total denied amount: <b>₱${d.total_amount_denied.toLocaleString()}</b><br>
            `;

            document.getElementById("aiReco").innerHTML = `
                • Audit high-denial provider: <b>${highestDenied.provider_name}</b><br>
                • Improve documentation to lower denial rates.<br>
                • Review insurer rejection patterns.<br>
                • Strengthen cross-department claim validation.<br>
            `;

            const denialRate = Math.round((d.total_denied_claims / d.total_claims) * 100);

            document.getElementById("aiForecast").innerHTML = `
                <b>Next Month Forecast:</b><br>
                • Expected Denial Rate: <b>${denialRate}%</b><br>
                • Recommended follow-up provider: <b>${highestDenied.provider_name}</b><br>
            `;
        }

        /* ---------------- EXPORT ---------------- */
        function exportExcel() {
            const wb = XLSX.utils.table_to_book(document.getElementById("providerTable"));
            XLSX.writeFile(wb, "InsuranceClaims.xlsx");
        }

        function exportPDF() {
            html2pdf().from(document.getElementById("reportArea")).save("InsuranceClaimsReport.pdf");
        }
    </script>

</body>

</html>