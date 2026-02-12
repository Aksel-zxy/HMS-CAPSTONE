<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Yearly Pharmacy Sales Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Minimal Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f7f7f7;
        }

        .month-card {
            border-radius: 10px;
        }

        .small-text {
            font-size: .85rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <!-- HEADER + YEAR SELECT -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-semibold mb-0">Pharmacy Sales Report</h4>
                <select id="yearSelector" class="form-select w-auto"></select>
            </div>

            <!-- SUMMARY -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Transactions</div>
                        <h5 id="totalTransactions">0</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Sales</div>
                        <h5 id="totalSales">₱0</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Top Item</div>
                        <h5 id="topItem">-</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Year</div>
                        <h5 id="yearLabel">-</h5>
                    </div>
                </div>
            </div>

            <!-- YEARLY CHART -->
            <div class="card p-4 mb-4" style="height:320px">
                <canvas id="yearChart" height="160"></canvas>
            </div>

            <!-- MONTH SUMMARY CARDS -->
            <div class="row g-3" id="monthCards"></div>

        </div>
    </div>

    <script>
        const baseApi = "https://localhost:7212/journal/getYearPharmacySalesReport/";
        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        let yearChart;

        // Populate year selector
        const yearSelect = document.getElementById("yearSelector");
        const currentYear = new Date().getFullYear();
        for (let y = currentYear; y >= currentYear - 5; y--) {
            yearSelect.innerHTML += `<option value="${y}">${y}</option>`;
        }

        yearSelect.addEventListener("change", () => loadReport(yearSelect.value));
        loadReport(yearSelect.value);

        function loadReport(year) {
            fetch(baseApi + year)
                .then(res => res.json())
                .then(data => {
                    document.getElementById("yearLabel").innerText = data.year;
                    document.getElementById("totalTransactions").innerText = data.totalTransactions;
                    document.getElementById("totalSales").innerText = "₱" + data.totalSales.toLocaleString();
                    document.getElementById("topItem").innerText = data.topSellingItem;

                    const labels = [];
                    const sales = [];
                    const container = document.getElementById("monthCards");
                    container.innerHTML = "";

                    const maxSale = Math.max(...data.monthSales.map(m => m.totalSales));

                    data.monthSales.forEach(m => {
                        labels.push(monthNames[m.month - 1]);
                        sales.push(m.totalSales);

                        const percent = ((m.totalSales / maxSale) * 100).toFixed(0);

                        // 🔹 Adjusted View button URL
                        container.innerHTML += `
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="card p-3 month-card">
                                    <h6 class="fw-semibold">${monthNames[m.month - 1]}</h6>
                                    <div class="small-text">Sales</div>
                                    <div class="fw-semibold">₱${m.totalSales.toLocaleString()}</div>
                                    <div class="small-text mt-1">Transactions</div>
                                    <div>${m.totalTransactions}</div>
                                    <div class="small-text mt-1">Top Item</div>
                                    <div>${m.topSellingItem}</div>
                                    <div class="progress mt-2" style="height:6px">
                                        <div class="progress-bar" style="width:${percent}%"></div>
                                    </div>
                                    <div class="d-flex justify-content-end mt-3">
                                        <a href="/backend/report_and_analytics/pharmacy_sales_report.php?month=${m.month}&year=${data.year}"
                                           class="btn btn-sm btn-outline-primary">View</a>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    if (yearChart) yearChart.destroy();

                    yearChart = new Chart(document.getElementById("yearChart"), {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                label: "Sales",
                                data: sales,
                                backgroundColor: "#0d6efd"
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    ticks: {
                                        callback: value => "₱" + value.toLocaleString()
                                    }
                                }
                            }
                        }
                    });
                });
        }
    </script>
</body>

</html>