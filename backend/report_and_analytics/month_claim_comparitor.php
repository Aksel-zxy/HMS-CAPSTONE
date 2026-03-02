<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Insurance Claims Comparison</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- SheetJS (Excel Export) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

    <!-- jsPDF (PDF Export) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        body {
            background: #f3f4f7;
            font-family: "Poppins", sans-serif;
        }

        .stat-card {
            border-radius: 18px;
            background: #fff;
            padding: 24px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
            border: none;
        }

        .chart-card {
            padding: 20px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
        }

        .stat-title {
            font-size: 14px;
            text-transform: uppercase;
            font-weight: 600;
            color: #6c757d;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
        }

        .export-btn {
            margin-right: 6px;
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <div class="container my-4">

            <h2 class="fw-bold mb-4 text-center">📊 Monthly Insurance Claims Comparison</h2>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Base Month</label>
                    <input type="month" id="baseMonthInput" class="form-control" value="2025-07">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Partner Month</label>
                    <input type="month" id="partnerMonthInput" class="form-control" value="2025-08">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-dark w-100" onclick="loadComparison()">Compare</button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4" id="statsCardsRow"></div>

            <!-- Charts -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">📊 Base vs Partner (Bar Chart)</h6>
                        <canvas id="barChart"></canvas>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">📈 Trend (Line Chart)</h6>
                        <canvas id="lineChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4 mx-auto">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">🥧 Claims Distribution (Pie Chart)</h6>
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="stat-card">
                <div class="d-flex justify-content-between mb-2">
                    <h5 class="fw-bold mb-0">📘 Comparison Table</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-primary export-btn" onclick="exportCSV()">CSV</button>
                        <button class="btn btn-sm btn-outline-success export-btn" onclick="exportExcel()">Excel</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="exportPDF()">PDF</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center" id="comparisonTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Metric</th>
                                <th id="baseMonthLabel">Base</th>
                                <th id="partnerMonthLabel">Partner</th>
                                <th>Difference (%)</th>
                            </tr>
                        </thead>
                        <tbody id="comparisonBody">
                            <tr>
                                <td colspan="4" class="text-muted">No data loaded</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary -->
            <div class="stat-card mt-4">
                <h5 class="fw-bold mb-2">📌 Summary</h5>
                <div id="comparisonSummary" class="text-muted">No summary available.</div>
            </div>

        </div>
    </div>

    <script>
        let barChart, lineChart, pieChart;

        function percentDiff(current, previous) {
            if (previous === 0) return {
                text: "N/A",
                class: "text-muted",
                value: 0
            };
            const diff = ((current - previous) / previous) * 100;
            return {
                text: diff > 0 ? `▲ ${diff.toFixed(2)}%` : diff < 0 ? `▼ ${Math.abs(diff).toFixed(2)}%` : "0%",
                class: diff > 0 ? "text-success" : diff < 0 ? "text-danger" : "text-muted",
                value: diff
            };
        }

        function createStatCards(data) {
            const row = document.getElementById("statsCardsRow");
            row.innerHTML = `
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-title">Total Claims</div>
                <div class="stat-value">${data.total.toLocaleString()}</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-title">Approved</div>
                <div class="stat-value">${data.approved.toLocaleString()}</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-title">Denied</div>
                <div class="stat-value">${data.denied.toLocaleString()}</div>
            </div>
        </div>
    `;
        }

        function buildSummary(d) {
            const total = percentDiff(d.partner_total_claims, d.base_total_claims);
            const approved = percentDiff(d.partner_total_approved_claims, d.base_total_approved_claims);
            const denied = percentDiff(d.partner_total_denied_claims, d.base_total_denied_claims);

            let summary = `
        <strong>${d.basemonth}/${d.baseyear} → ${d.partnermonth}/${d.partneryear}</strong><br><br>
        • Total Claims: <span class="${total.class}">${total.text}</span><br>
        • Approved: <span class="${approved.class}">${approved.text}</span><br>
        • Denied: <span class="${denied.class}">${denied.text}</span><br>
    `;

            document.getElementById("comparisonSummary").innerHTML = summary;
        }

        function renderCharts(d) {
            const labels = ["Total Claims", "Approved", "Denied"];
            const base = [d.base_total_claims, d.base_total_approved_claims, d.base_total_denied_claims];
            const partner = [d.partner_total_claims, d.partner_total_approved_claims, d.partner_total_denied_claims];

            // BAR
            if (barChart) barChart.destroy();
            barChart = new Chart(document.getElementById("barChart"), {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                            label: "Base",
                            data: base
                        },
                        {
                            label: "Partner",
                            data: partner
                        }
                    ]
                }
            });

            // LINE
            if (lineChart) lineChart.destroy();
            lineChart = new Chart(document.getElementById("lineChart"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                            label: "Base",
                            data: base,
                            tension: .4
                        },
                        {
                            label: "Partner",
                            data: partner,
                            tension: .4
                        }
                    ]
                }
            });

            // PIE
            if (pieChart) pieChart.destroy();
            pieChart = new Chart(document.getElementById("pieChart"), {
                type: "pie",
                data: {
                    labels,
                    datasets: [{
                        data: partner
                    }]
                }
            });
        }

        async function loadComparison() {
            const baseVal = document.getElementById("baseMonthInput").value;
            const partnerVal = document.getElementById("partnerMonthInput").value;

            const [baseYear, baseMonth] = baseVal.split("-").map(Number);
            const [partnerYear, partnerMonth] = partnerVal.split("-").map(Number);

            document.getElementById("baseMonthLabel").innerText = `${baseMonth}/${baseYear}`;
            document.getElementById("partnerMonthLabel").innerText = `${partnerMonth}/${partnerYear}`;

            const tbody = document.getElementById("comparisonBody");
            tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

            try {
                const res = await fetch(
                    `https://bsis-03.keikaizen.xyz/insurance/monthClaimComparisonEndpoint?month=${baseMonth}&year=${baseYear}&partnerMonth=${partnerMonth}&partnerYear=${partnerYear}`
                );

                const d = await res.json();
                tbody.innerHTML = "";

                const rows = [
                    ["Total Claims", d.base_total_claims, d.partner_total_claims],
                    ["Approved Claims", d.base_total_approved_claims, d.partner_total_approved_claims],
                    ["Denied Claims", d.base_total_denied_claims, d.partner_total_denied_claims]
                ];

                // Stats Cards
                createStatCards({
                    total: d.partner_total_claims,
                    approved: d.partner_total_approved_claims,
                    denied: d.partner_total_denied_claims
                });

                // Table
                rows.forEach(r => {
                    const diff = percentDiff(r[2], r[1]);
                    tbody.innerHTML += `
                <tr>
                    <td class="fw-semibold">${r[0]}</td>
                    <td>${r[1]}</td>
                    <td>${r[2]}</td>
                    <td class="${diff.class}">${diff.text}</td>
                </tr>
            `;
                });

                buildSummary(d);
                renderCharts(d);

            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-danger">Error loading data</td></tr>`;
            }
        }

        /* EXPORT FUNCTIONS */
        function exportCSV() {
            let csv = [];
            document.querySelectorAll("#comparisonTable tr").forEach(row => {
                const cols = [...row.children].map(c => `"${c.innerText}"`);
                csv.push(cols.join(","));
            });
            const blob = new Blob([csv.join("\n")], {
                type: "text/csv"
            });
            const a = document.createElement("a");
            a.href = URL.createObjectURL(blob);
            a.download = "insurance_claims_comparison.csv";
            a.click();
        }

        function exportExcel() {
            const table = document.getElementById("comparisonTable");
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, "insurance_claims_comparison.xlsx");
        }

        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF();
            pdf.text("Insurance Claims Comparison", 10, 10);
            pdf.html(document.getElementById("comparisonTable"), {
                callback: function() {
                    pdf.save("insurance_claims_comparison.pdf");
                }
            });
        }

        loadComparison();
    </script>

</body>

</html>