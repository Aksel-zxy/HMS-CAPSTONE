<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Revenue Range Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background-color: #f4f6f9;
            padding: 30px;
        }

        main.content {
            max-width: 1300px;
            margin: 0 auto;
        }

        .card {
            border-radius: 14px;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            transition: 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .metric {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .month-card {
            min-width: 250px;
            flex: 1 1 220px;
        }

        .chart-container {
            height: 160px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <main class="content">

            <!-- TITLE -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <h3 class="mb-0">📊 Revenue Range Report</h3>
            </div>

            <!-- RANGE FILTER -->
            <div class="row g-3 mb-4">

                <div class="col-md-3">
                    <label class="form-label">From Month</label>
                    <select id="fromMonth" class="form-select">
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
                    <label class="form-label">From Year</label>
                    <input type="number" id="fromYear" class="form-control" value="2024">
                </div>

                <div class="col-md-3">
                    <label class="form-label">To Month</label>
                    <select id="toMonth" class="form-select">
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
                    <label class="form-label">To Year</label>
                    <input type="number" id="toYear" class="form-control" value="2025">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-dark w-100" onclick="loadRange()">Apply Range</button>
                </div>
            </div>

            <!-- KPI CARDS -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card p-4 text-center bg-primary text-white">
                        <div class="metric" id="totalRevenue">₱0</div>
                        <small>Total Revenue (Range)</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 text-center bg-success text-white">
                        <div class="metric" id="serviceRevenue">₱0</div>
                        <small>Total Service Revenue</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 text-center bg-info text-white">
                        <div class="metric" id="pharmacyRevenue">₱0</div>
                        <small>Total Pharmacy Revenue</small>
                    </div>
                </div>
            </div>

            <!-- GLOBAL CHARTS -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card p-3">
                        <h6 class="fw-bold mb-2">📊 Revenue per Month (Bar Chart)</h6>
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card p-3">
                        <h6 class="fw-bold mb-2">📈 Revenue Trend (Line Chart)</h6>
                        <canvas id="lineChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- PIE CHART -->
            <div class="row mb-4">
                <div class="col-lg-4 mx-auto">
                    <div class="card p-3">
                        <h6 class="fw-bold mb-2">🥧 Overall Distribution</h6>
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- STATISTICAL SUMMARY -->
            <div class="card p-4 mb-4">
                <h5 class="fw-bold mb-3">📌 Statistical Summary</h5>
                <div id="summaryText" class="text-secondary"></div>
            </div>

            <!-- MONTHLY BREAKDOWN -->
            <h5 class="mb-3">Monthly Breakdown</h5>
            <div class="d-flex flex-wrap gap-3" id="monthlyBreakdown"></div>

        </main>
    </div>

    <script>
        const API_RANGE = "https://bsis-03.keikaizen.xyz/journal/getRevenueRange";
        const monthNames = ["January", "February", "March", "April", "May", "June", "July",
            "August", "September", "October", "November", "December"
        ];

        let barChart, lineChart, pieChart;

        const formatCurrency = v =>
            "₱" + Number(v).toLocaleString("en-PH", {
                minimumFractionDigits: 2
            });

        async function loadRange() {
            const fromM = Number(document.getElementById("fromMonth").value);
            const fromY = Number(document.getElementById("fromYear").value);
            const toM = Number(document.getElementById("toMonth").value);
            const toY = Number(document.getElementById("toYear").value);

            const fromValue = fromY * 12 + fromM;
            const toValue = toY * 12 + toM;

            if (fromValue > toValue) {
                alert("Invalid range: 'From' must be earlier than 'To'.");
                return;
            }

            const res = await fetch(
                `${API_RANGE}?fromMonth=${fromM}&fromYear=${fromY}&toMonth=${toM}&toYear=${toY}`
            );

            const data = await res.json();

            // UPDATE KPI
            document.getElementById("totalRevenue").innerText = formatCurrency(data.totalRevenue);
            document.getElementById("serviceRevenue").innerText = formatCurrency(data.totalService);
            document.getElementById("pharmacyRevenue").innerText = formatCurrency(data.totalPharmacy);

            // GLOBAL SUMMARY
            const highest = data.months.reduce((a, b) =>
                (a.service_revenue + a.pharmacy_revenue >
                    b.service_revenue + b.pharmacy_revenue ? a : b)
            );

            const lowest = data.months.reduce((a, b) =>
                (a.service_revenue + a.pharmacy_revenue <
                    b.service_revenue + b.pharmacy_revenue ? a : b)
            );

            const average = data.totalRevenue / data.months.length;

            document.getElementById("summaryText").innerHTML = `
                <strong>Highest Month:</strong> ${monthNames[highest.month - 1]} ${highest.year} 
                (${formatCurrency(highest.service_revenue + highest.pharmacy_revenue)})<br>

                <strong>Lowest Month:</strong> ${monthNames[lowest.month - 1]} ${lowest.year}
                (${formatCurrency(lowest.service_revenue + lowest.pharmacy_revenue)})<br>

                <strong>Average Monthly Revenue:</strong> ${formatCurrency(average)}<br>

                <strong>Service Contribution:</strong> 
                ${(data.totalService / data.totalRevenue * 100).toFixed(1)}%<br>

                <strong>Pharmacy Contribution:</strong> 
                ${(data.totalPharmacy / data.totalRevenue * 100).toFixed(1)}%<br>
            `;

            // GLOBAL CHART DATA
            const labels = data.months.map(m => `${monthNames[m.month - 1]} ${m.year}`);
            const service = data.months.map(m => m.service_revenue);
            const pharmacy = data.months.map(m => m.pharmacy_revenue);
            const totals = data.months.map(m => m.service_revenue + m.pharmacy_revenue);

            // BAR CHART
            if (barChart) barChart.destroy();
            barChart = new Chart(barChart, {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                            label: "Service",
                            data: service
                        },
                        {
                            label: "Pharmacy",
                            data: pharmacy
                        },
                    ]
                }
            });

            // LINE CHART
            if (lineChart) lineChart.destroy();
            lineChart = new Chart(lineChart, {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        label: "Total Revenue",
                        data: totals,
                        tension: 0.4
                    }]
                }
            });

            // PIE CHART
            if (pieChart) pieChart.destroy();
            pieChart = new Chart(pieChart, {
                type: "pie",
                data: {
                    labels: ["Service", "Pharmacy"],
                    datasets: [{
                        data: [data.totalService, data.totalPharmacy]
                    }]
                }
            });

            // MONTHLY BREAKDOWN
            const container = document.getElementById("monthlyBreakdown");
            container.innerHTML = "";

            data.months.forEach((m, idx) => {
                const total = m.service_revenue + m.pharmacy_revenue;

                const card = document.createElement("div");
                card.className = "card p-3 month-card text-center";

                card.innerHTML = `
                    <h6>${monthNames[m.month - 1]} ${m.year}</h6>
                    <div class="metric">${formatCurrency(total)}</div>
                    <small>Service: ${formatCurrency(m.service_revenue)}</small><br/>
                    <small>Pharmacy: ${formatCurrency(m.pharmacy_revenue)}</small>
                    <div class="chart-container mt-2"><canvas id="mini-${idx}"></canvas></div>
                `;

                container.appendChild(card);

                new Chart(document.getElementById(`mini-${idx}`), {
                    type: "doughnut",
                    data: {
                        labels: ["Service", "Pharmacy"],
                        datasets: [{
                            data: [m.service_revenue, m.pharmacy_revenue]
                        }]
                    }
                });
            });
        }
    </script>
</body>

</html>