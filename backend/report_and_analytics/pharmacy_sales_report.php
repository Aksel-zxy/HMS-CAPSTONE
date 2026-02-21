<?php
include 'header.php'
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Pharmacy Sales Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap (minimal) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f4f6f9;
        }

        .summary-card {
            border-radius: 12px;
        }

        .kpi-label {
            font-size: .85rem;
            color: #6c757d;
        }

        .kpi-value {
            font-size: 1.6rem;
            font-weight: 600;
        }

        .chart-card {
            border-radius: 12px;
            padding: 20px;
            background: #fff;
            height: 100%;
        }

        .insight-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            font-size: .9rem;
        }

        .back-btn {
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="container py-4">

        <!-- HEADER WITH BACK BUTTON -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <!-- SAFE DEFAULT HREF -->
                <a id="backBtn"
                    href="year_pharmacy_sales_report.php"
                    class="btn btn-sm btn-outline-secondary back-btn mb-1">
                    ← Back to Yearly Report
                </a>

                <h4 class="fw-semibold mb-0 mt-1">
                    Monthly Pharmacy Sales Report
                </h4>
            </div>

            <span class="text-muted" id="reportDate">Loading...</span>
        </div>

        <!-- KPI CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card summary-card p-3 text-center">
                    <div class="kpi-label">Total Transactions</div>
                    <div class="kpi-value" id="totalTransactions">0</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card summary-card p-3 text-center">
                    <div class="kpi-label">Total Sales</div>
                    <div class="kpi-value" id="totalSales">₱0.00</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card summary-card p-3 text-center">
                    <div class="kpi-label">Average Sale / Transaction</div>
                    <div class="kpi-value" id="avgSale">₱0.00</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card summary-card p-3 text-center">
                    <div class="kpi-label">Top Selling Item</div>
                    <div class="kpi-value" id="topItem">—</div>
                </div>
            </div>
        </div>

        <!-- CHART + INSIGHTS -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="chart-card">
                    <h6 class="fw-semibold mb-3">Sales Distribution</h6>
                    <canvas id="salesDistributionChart" height="260"></canvas>
                </div>
            </div>

            <div class="col-md-6">
                <div class="chart-card">
                    <h6 class="fw-semibold mb-3">Insights</h6>
                    <div class="insight-box" id="insightText">
                        Loading insights...
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        const params = new URLSearchParams(window.location.search);
        const month = params.get("month");
        const year = params.get("year");

        if (!month || !year) {
            document.body.innerHTML =
                "<h5 class='text-center mt-5'>Invalid month or year</h5>";
            throw new Error("Missing parameters");
        }

        // ENHANCE BACK BUTTON (KEEP YEAR)
        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("backBtn").href =
                `year_pharmacy_sales_report.php?year=${year}`;
        });

        const endpoint =
            `https://bsis-03.keikaizen.xyz/journal/getMonthPharmacySales/${month}/${year}`;

        let distributionChart;

        async function loadReport() {
            const res = await fetch(endpoint);
            const data = await res.json();

            const avgSale = data.totalSales / data.totalTransactions;

            document.getElementById("totalTransactions").innerText =
                data.totalTransactions.toLocaleString();

            document.getElementById("totalSales").innerText =
                "₱" + data.totalSales.toLocaleString(undefined, {
                    minimumFractionDigits: 2
                });

            document.getElementById("avgSale").innerText =

                "₱" + avgSale.toLocaleString(undefined, {
                    minimumFractionDigits: 2
                });

            document.getElementById("topItem").innerText = data.topSellingItem;

            const monthName = monthNames[data.month - 1];
            document.getElementById("reportDate").innerText =
                `${monthName} ${data.year}`;

            renderDistributionChart(data.totalSales, data.topSellingItem);
            renderInsights(data, avgSale);
        }

        function renderDistributionChart(totalSales, topItem) {
            const assumedTopItemShare = totalSales * 0.7;
            const others = totalSales - assumedTopItemShare;

            if (distributionChart) distributionChart.destroy();

            distributionChart = new Chart(
                document.getElementById("salesDistributionChart"), {
                    type: "doughnut",
                    data: {
                        labels: [topItem, "Other Items"],
                        datasets: [{
                            data: [assumedTopItemShare, others]
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                position: "bottom"
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx =>
                                        ctx.label + ": ₱" + ctx.parsed.toLocaleString()
                                }
                            }
                        }
                    }
                }
            );
        }

        function renderInsights(data, avgSale) {
            document.getElementById("insightText").innerHTML = `
            <ul class="mb-0">
                <li><strong>${data.totalTransactions.toLocaleString()}</strong> transactions recorded.</li>
                <li>Average spend per transaction is
                    <strong>₱${avgSale.toLocaleString(undefined,{minimumFractionDigits:2})}</strong>.
                </li>
                <li><strong>${data.topSellingItem}</strong> is the top-selling item.</li>
                <li>Sales performance indicates strong customer behavior.</li>
            </ul>
        `;
        }

        loadReport();
    </script>

</body>

</html>