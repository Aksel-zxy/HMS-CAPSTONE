<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Revenue Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Premium Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

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
            transition: transform 0.2s ease-in-out;
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

        .summary-text {
            font-size: 0.9rem;
            margin-top: 8px;
            color: #495057;
        }

        .back-btn {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?
        include 'sidebar.php'
        ?>
        <main class="content">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                    <h3 class="mb-0">Monthly Revenue Report</h3>
                </div>
                <select id="yearSelect" class="form-select w-auto"></select>
            </div>

            <!-- KPI CARDS -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card p-4 text-center bg-primary text-white">
                        <div class="metric" id="yearRevenue">₱0</div>
                        <small>Total Revenue (Year)</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 text-center bg-success text-white">
                        <div class="metric" id="serviceRevenue">₱0</div>
                        <small>Service Revenue</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 text-center bg-info text-white">
                        <div class="metric" id="pharmacyRevenue">₱0</div>
                        <small>Pharmacy Revenue</small>
                    </div>
                </div>
            </div>

            <!-- Monthly Breakdown -->
            <h5 class="mb-3">Monthly Breakdown</h5>
            <div class="d-flex flex-wrap gap-3" id="monthlyBreakdown"></div>

        </main>

        <script>
            const API_BASE = "https://localhost:7212/journal/getMonthsDetailsRevenueReport";
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            const formatCurrency = v =>
                "₱" + Number(v).toLocaleString("en-PH", {
                    minimumFractionDigits: 2
                });

            function populateYears() {
                const select = document.getElementById("yearSelect");
                const currentYear = new Date().getFullYear();
                for (let y = currentYear; y >= currentYear - 5; y--) {
                    select.add(new Option(y, y));
                }
            }

            async function loadRevenue(year) {
                const res = await fetch(`${API_BASE}?year=${year}`);
                const data = await res.json();

                document.getElementById("yearRevenue").innerText = formatCurrency(data.yearTotalRevenue);

                let service = 0,
                    pharmacy = 0;
                const container = document.getElementById("monthlyBreakdown");
                container.innerHTML = "";

                data.monthsRevenue.forEach((m, idx) => {
                    service += m.service_revenue;
                    pharmacy += m.pharmacy_revenue;
                    const total = m.service_revenue + m.pharmacy_revenue;

                    const servicePct = ((m.service_revenue / total) * 100).toFixed(1);
                    const pharmacyPct = ((m.pharmacy_revenue / total) * 100).toFixed(1);

                    const card = document.createElement("div");
                    card.className = "card p-3 month-card text-center";

                    card.innerHTML = `
                    <h6 class="mb-2">${monthNames[m.month - 1]}</h6>
                    <div class="metric mb-1">${formatCurrency(total)}</div>
                    <small>Service: ${formatCurrency(m.service_revenue)}</small><br>
                    <small>Pharmacy: ${formatCurrency(m.pharmacy_revenue)}</small>

                    <div class="chart-container mt-2">
                        <canvas id="chart-${idx}"></canvas>
                    </div>

                    <div class="summary-text">
                        Pharmacy (Rx) contributed <strong>${pharmacyPct}%</strong> of total revenue,
                        Service contributed <strong>${servicePct}%</strong>.
                    </div>
                `;

                    container.appendChild(card);

                    new Chart(document.getElementById(`chart-${idx}`), {
                        type: 'doughnut',
                        data: {
                            labels: ['Service', 'Pharmacy'],
                            datasets: [{
                                data: [m.service_revenue, m.pharmacy_revenue],
                                backgroundColor: ['#4e73df', '#1cc88a']
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => `${ctx.label}: ${formatCurrency(ctx.raw)}`
                                    }
                                }
                            }
                        }
                    });
                });

                document.getElementById("serviceRevenue").innerText = formatCurrency(service);
                document.getElementById("pharmacyRevenue").innerText = formatCurrency(pharmacy);
            }

            populateYears();
            loadRevenue(document.getElementById("yearSelect").value);
            document.getElementById("yearSelect")
                .addEventListener("change", e => loadRevenue(e.target.value));
        </script>
    </div>
</body>

</html>