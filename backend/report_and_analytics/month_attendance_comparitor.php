<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Attendance Analytics Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & Icons -->
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

            <h2 class="fw-bold mb-4 text-center">📊 Attendance Analytics Dashboard</h2>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Base Month</label>
                    <input type="month" id="baseMonthInput" class="form-control" value="2025-06">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Partner Month</label>
                    <input type="month" id="partnerMonthInput" class="form-control" value="2025-07">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-dark w-100" onclick="loadComparison()">Compare</button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4" id="statsCardsRow"></div>

            <!-- Charts Section -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">📊 Base vs Partner (Bar Chart)</h6>
                        <canvas id="barChart"></canvas>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">📈 Trend Line Chart</h6>
                        <canvas id="lineChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4 mx-auto">
                    <div class="chart-card">
                        <h6 class="fw-bold mb-2">🥧 Attendance Distribution (Pie)</h6>
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Comparison Table -->
            <div class="stat-card mt-4">
                <div class="d-flex justify-content-between mb-2">
                    <h5 class="fw-bold mb-0">📘 Comparison Table</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-primary export-btn" onclick="exportCSV()">CSV</button>
                        <button class="btn btn-sm btn-outline-success export-btn" onclick="exportExcel()">Excel</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="exportPDF()">PDF</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center mb-0" id="comparisonTable">
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

            <!-- Summary -->
            <div class="stat-card mt-4">
                <h5 class="fw-bold mb-2">📌 Summary</h5>
                <div id="comparisonSummary" class="text-muted">
                    No summary available.
                </div>
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
                        <div class="stat-title">Present</div>
                        <div class="stat-value">${data.present}</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-title">Late</div>
                        <div class="stat-value">${data.late}</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-title">Undertime</div>
                        <div class="stat-value">${data.undertime}</div>
                    </div>
                </div>
            `;
        }

        function buildSummary(rows, baseLabel, partnerLabel) {
            let summary = `<strong>${baseLabel} vs ${partnerLabel}</strong><br>`;
            let mostChanged = {
                name: "",
                value: 0
            };

            rows.forEach(r => {
                const diff = percentDiff(r[2], r[1]);
                if (Math.abs(diff.value) > mostChanged.value) {
                    mostChanged = {
                        name: r[0],
                        value: Math.abs(diff.value)
                    };
                }

                summary += diff.value > 0 ?
                    `• <strong>${r[0]}</strong> increased by <span class="text-success">${diff.value.toFixed(2)}%</span><br>` :
                    diff.value < 0 ?
                    `• <strong>${r[0]}</strong> decreased by <span class="text-danger">${Math.abs(diff.value).toFixed(2)}%</span><br>` :
                    `• <strong>${r[0]}</strong> remained unchanged<br>`;
            });

            summary += `<br>Most significant change: <strong>${mostChanged.name}</strong>.`;

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
                            label: "Base Month",
                            data: base,
                            tension: 0.4
                        },
                        {
                            label: "Partner Month",
                            data: partner,
                            tension: 0.4
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
                    `https://localhost:7212/employee/monthAttendanceComparisonEndpoint?baseMonth=${baseMonth}&baseYear=${baseYear}&partnerMonth=${partnerMonth}&partnerYear=${partnerYear}`
                );

                if (!res.ok) throw new Error(await res.text());

                const d = await res.json();

                const rows = [
                    ["Present", d.basePresent, d.partnerPresent],
                    ["Late", d.baseLate, d.partnerLate],
                    ["Undertime", d.baseUnderTime, d.partnerUnderTime]
                ];

                // Stats cards
                createStatCards({
                    present: d.partnerPresent,
                    late: d.partnerLate,
                    undertime: d.partnerUnderTime
                });

                tbody.innerHTML = "";
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

                buildSummary(rows, `${baseMonth}/${baseYear}`, `${partnerMonth}/${partnerYear}`);
                renderCharts(rows);

            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-danger">${err.message}</td></tr>`;
            }
        }

        // EXPORT FUNCTIONS
        function exportCSV() {
            let csv = [];
            const rows = document.querySelectorAll("#comparisonTable tr");
            rows.forEach(row => {
                const cols = [...row.children].map(col => `"${col.innerText}"`);
                csv.push(cols.join(","));
            });
            const blob = new Blob([csv.join("\n")], {
                type: "text/csv"
            });
            const url = URL.createObjectURL(blob);

            const a = document.createElement("a");
            a.href = url;
            a.download = "attendance_comparison.csv";
            a.click();
        }

        function exportExcel() {
            const table = document.getElementById("comparisonTable");
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, "attendance_comparison.xlsx");
        }

        async function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF();
            pdf.text("Attendance Comparison Report", 10, 10);
            pdf.html(document.getElementById("comparisonTable"), {
                callback: function() {
                    pdf.save("attendance_comparison.pdf");
                }
            });
        }

        loadComparison();
    </script>
</body>

</html>