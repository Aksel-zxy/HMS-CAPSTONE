<?php include 'header.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Billing Comparison</title>
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
            font-size: 30px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include 'sidebar.php' ?>

        <div class="container my-4">

            <h2 class="fw-bold mb-4 text-center">📊 Monthly Billing Comparison</h2>

            <!-- FILTERS -->
            <div class="row g-3 mb-4 justify-content-center">

                <div class="col-md-3">
                    <label class="form-label">Base Month</label>
                    <select id="firstMonth" class="form-select">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Base Year</label>
                    <input type="number" id="firstYear" class="form-control" value="2025">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Partner Month</label>
                    <select id="secondMonth" class="form-select">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Partner Year</label>
                    <input type="number" id="secondYear" class="form-control" value="2025">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-dark w-100" onclick="loadComparison()">Compare</button>
                </div>
            </div>

            <!-- STATS CARDS -->
            <div class="row g-4 mb-4" id="statsCardsRow"></div>

            <!-- CHARTS -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">📊 Base vs Partner (Bar Chart)</h6>
                        <canvas id="barChart"></canvas>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">📈 Billing Trend (Line Chart)</h6>
                        <canvas id="lineChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4 mx-auto">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">🥧 Billing Distribution (Pie)</h6>
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TABLE -->
            <div class="stat-card">
                <div class="d-flex justify-content-between mb-2">
                    <h5 class="fw-bold mb-0">📘 Comparison Table</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportCSV()">CSV</button>
                        <button class="btn btn-sm btn-outline-success" onclick="exportExcel()">Excel</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="exportPDF()">PDF</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center" id="comparisonTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Metric</th>
                                <th id="baseMonthLabel">Base Month</th>
                                <th id="partnerMonthLabel">Partner Month</th>
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

            <!-- SUMMARY -->
            <div class="stat-card mt-4">
                <h5 class="fw-bold mb-2">📌 Summary</h5>
                <div id="comparisonSummary" class="text-muted">No summary available.</div>
            </div>

        </div>
    </div>

    <script>
        let barChart, lineChart, pieChart;

        const monthNames = ["January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

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
            <div class="stat-card"><div class="stat-title">Total Billed</div>
            <div class="stat-value">₱${data.total_billed.toLocaleString()}</div></div>
        </div>

        <div class="col-md-4">
            <div class="stat-card"><div class="stat-title">Total Paid</div>
            <div class="stat-value">₱${data.total_paid.toLocaleString()}</div></div>
        </div>

        <div class="col-md-4">
            <div class="stat-card"><div class="stat-title">Pending</div>
            <div class="stat-value">₱${data.total_pending.toLocaleString()}</div></div>
        </div>
    `;
        }

        function buildSummary(rows, baseLabel, partnerLabel) {
            let summary = `<strong>${baseLabel} vs ${partnerLabel}</strong><br>`;
            let most = {
                name: "",
                value: 0
            };

            rows.forEach(r => {
                const diff = percentDiff(r[2], r[1]);
                if (Math.abs(diff.value) > Math.abs(most.value))
                    most = {
                        name: r[0],
                        value: diff.value
                    };

                summary += diff.value > 0 ?
                    `• <b>${r[0]}</b> increased by <span class="text-success">${diff.value.toFixed(2)}%</span><br>` :
                    diff.value < 0 ?
                    `• <b>${r[0]}</b> decreased by <span class="text-danger">${Math.abs(diff.value).toFixed(2)}%</span><br>` :
                    `• <b>${r[0]}</b> remained unchanged<br>`;
            });

            summary += `<br>Most significant change: <strong>${most.name}</strong>.`;
            document.getElementById("comparisonSummary").innerHTML = summary;
        }

        function renderCharts(rows) {
            const labels = rows.map(r => r[0]);
            const base = rows.map(r => r[1]);
            const partner = rows.map(r => r[2]);

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
            const firstMonth = document.getElementById("firstMonth").value;
            const firstYear = document.getElementById("firstYear").value;
            const secondMonth = document.getElementById("secondMonth").value;
            const secondYear = document.getElementById("secondYear").value;

            const baseLabel = `${monthNames[firstMonth - 1]} ${firstYear}`;
            const partnerLabel = `${monthNames[secondMonth - 1]} ${secondYear}`;

            document.getElementById("baseMonthLabel").innerText = baseLabel;
            document.getElementById("partnerMonthLabel").innerText = partnerLabel;

            const tbody = document.getElementById("comparisonBody");
            tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

            try {
                const url =
                    `https://bsis-03.keikaizen.xyz/journal/monthBillingReportComparisonEndpoint?` +
                    `firstMoth=${firstMonth}&firstYear=${firstYear}` +
                    `&secondMonth=${secondMonth}&secondYear=${secondYear}`;

                const res = await fetch(url);
                const d = await res.json();

                const rows = [
                    ["Total Billed", d.total_billed, d.partnertotal_billed],
                    ["Total Paid", d.total_paid, d.partnertotal_paid],
                    ["Pending Transactions", d.total_pending_transaction, d.partnertotal_pending_transaction],
                    ["OOP Collected", d.total_oop_collected, d.partnertotal_oop_collected],
                    ["Insurance Covered", d.total_insurance_covered, d.partnertotal_insurance_covered],
                    ["Pending Amount", d.total_pending_amount, d.partnertotal_pending_amount]
                ];

                createStatCards({
                    total_billed: d.partnertotal_billed,
                    total_paid: d.partnertotal_paid,
                    total_pending: d.partnertotal_pending_amount
                });

                tbody.innerHTML = "";
                rows.forEach(r => {
                    const diff = percentDiff(r[2], r[1]);
                    tbody.innerHTML += `
                <tr>
                    <td class="fw-semibold">${r[0]}</td>
                    <td>₱${r[1].toLocaleString()}</td>
                    <td>₱${r[2].toLocaleString()}</td>
                    <td class="${diff.class}">${diff.text}</td>
                </tr>
            `;
                });

                buildSummary(rows, baseLabel, partnerLabel);
                renderCharts(rows);

            } catch (err) {
                tbody.innerHTML =
                    `<tr><td colspan="4" class="text-danger">Error loading data</td></tr>`;
                document.getElementById("comparisonSummary").innerHTML =
                    "Unable to generate summary.";
            }
        }

        // EXPORTS
        function exportCSV() {
            let csv = [];
            const rows = document.querySelectorAll("#comparisonTable tr");
            rows.forEach(r => {
                const cols = [...r.children].map(c => `"${c.innerText}"`);
                csv.push(cols.join(","));
            });
            const blob = new Blob([csv.join("\n")], {
                type: "text/csv"
            });
            const a = document.createElement("a");
            a.href = URL.createObjectURL(blob);
            a.download = "billing_comparison.csv";
            a.click();
        }

        function exportExcel() {
            const table = document.getElementById("comparisonTable");
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, "billing_comparison.xlsx");
        }

        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF();
            pdf.text("Billing Comparison Report", 10, 10);
            pdf.html(document.getElementById("comparisonTable"), {
                callback: function() {
                    pdf.save("billing_comparison.pdf");
                }
            });
        }

        loadComparison();
    </script>

</body>

</html>