<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Billing Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        .summary-card {
            min-height: 120px;
        }

        .chart-container {
            height: 220px;
        }
    </style>
</head>

<body class="bg-light">
    <div class="d-flex">
        <?php include "sidebar.php"; ?>
        <div class="container py-4">

            <h2 class="mb-4">Monthly Billing Report</h2>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-2">
                    <div class="card shadow-sm summary-card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Total Billed</h6>
                            <h5 id="totalBilled" class="text-primary">₱ 0</h5>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm summary-card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Total Paid</h6>
                            <h5 id="totalPaid" class="text-success">₱ 0</h5>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm summary-card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Pending Count</h6>
                            <h5 id="totalPending" class="text-danger">0</h5>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm summary-card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Pending Amount</h6>
                            <h5 id="totalPendingAmount" class="text-danger">₱ 0</h5>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm summary-card text-center">
                        <div class="card-body">
                            <h6 class="card-title">OOP Collected</h6>
                            <h5 id="totalOOP" class="text-info">₱ 0</h5>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm summary-card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Insurance Covered</h6>
                            <h5 id="totalInsurance" class="text-warning">₱ 0</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="text-center mb-3">Billing Distribution (OOP vs Insurance vs Pending Amount)</h5>
                <div class="chart-container">
                    <canvas id="billingChart"></canvas>
                </div>
            </div>

            <!-- Monthly Transactions Table -->
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="mb-3">Transactions Throughout the Month</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered align-middle" id="transactionsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Total Billed</th>
                                <th>Total Paid</th>
                                <th>Pending Transactions</th>
                                <th>Pending Amount</th>
                                <th>OOP Collected</th>
                                <th>Insurance Covered</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <nav>
                    <ul class="pagination justify-content-center mt-3">
                        <li class="page-item" id="prevPage"><a class="page-link" href="#">Previous</a></li>
                        <li class="page-item" id="nextPage"><a class="page-link" href="#">Next</a></li>
                    </ul>
                </nav>
            </div>

        </div>
    </div>

    <script>
        Chart.register(ChartDataLabels);

        let billingChart;
        let currentPage = 1;
        const pageSize = 5;

        // Read month and year from URL query parameters
        const urlParams = new URLSearchParams(window.location.search);
        const month = urlParams.get('month');
        const year = urlParams.get('year');

        async function loadBillingData() {
            if (!month || !year) {
                alert("Month or Year not provided in URL.");
                return;
            }

            const summaryUrl = `https://localhost:7212/journal/getMonthBillingReport/${month}/${year}`;
            const tableUrl = `https://localhost:7212/journal/getMonthTransactions/${month}/${year}?page=${currentPage}&size=${pageSize}`;

            try {
                const res = await fetch(summaryUrl);
                const data = await res.json();

                // Summary Cards
                document.getElementById('totalBilled').innerText = `₱ ${Number(data.total_billed).toLocaleString()}`;
                document.getElementById('totalPaid').innerText = `₱ ${Number(data.total_paid).toLocaleString()}`;
                document.getElementById('totalPending').innerText = data.total_pending_transactions ?? 0;
                document.getElementById('totalPendingAmount').innerText = `₱ ${Number(data.total_pending_amount).toLocaleString()}`;
                document.getElementById('totalOOP').innerText = `₱ ${Number(data.total_oop_collected).toLocaleString()}`;
                document.getElementById('totalInsurance').innerText = `₱ ${Number(data.total_insurance_covered).toLocaleString()}`;

                // Chart
                const chartData = [data.total_oop_collected, data.total_insurance_covered, data.total_pending_amount];
                const chartLabels = ["OOP", "Insurance", "Pending Amount"];
                const chartColors = ["#0dcaf0", "#ffc107", "#dc3545"];
                const total = chartData.reduce((a, b) => a + b, 0);

                if (billingChart) billingChart.destroy();
                billingChart = new Chart(document.getElementById('billingChart'), {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            data: chartData,
                            backgroundColor: chartColors
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            datalabels: {
                                color: "#000",
                                anchor: 'end',
                                align: 'top',
                                formatter: (value) => total ? ((value / total) * 100).toFixed(1) + "%" : "0%"
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });

                // Transactions Table
                const tableRes = await fetch(tableUrl);
                const transactions = await tableRes.json();
                const tbody = document.querySelector('#transactionsTable tbody');
                tbody.innerHTML = '';

                transactions.forEach(tx => {
                    const tr = document.createElement('tr');
                    tr.classList.toggle('table-danger', tx.total_pending_transactions > 0);
                    tr.innerHTML = `
                <td>${tx.report_date}</td>
                <td>₱ ${Number(tx.total_billed).toLocaleString()}</td>
                <td>₱ ${Number(tx.total_paid).toLocaleString()}</td>
                <td>${tx.total_pending_transactions}</td>
                <td>₱ ${Number(tx.total_pending_amount).toLocaleString()}</td>
                <td>₱ ${Number(tx.total_oop_collected).toLocaleString()}</td>
                <td>₱ ${Number(tx.total_insurance_covered).toLocaleString()}</td>
                <td>
                    <a href="daily_billing_report.php?date=${tx.report_date}" class="btn btn-primary btn-sm">View</a>
                </td>
            `;
                    tbody.appendChild(tr);
                });

            } catch (err) {
                console.error("Failed to load billing data:", err);
                alert("Failed to fetch monthly billing report. Check API or CORS.");
            }
        }

        // Pagination handlers
        async function nextPage() {
            currentPage++;
            await loadBillingData();
        }
        async function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                await loadBillingData();
            }
        }
        document.getElementById('nextPage').addEventListener('click', e => {
            e.preventDefault();
            nextPage();
        });
        document.getElementById('prevPage').addEventListener('click', e => {
            e.preventDefault();
            prevPage();
        });

        loadBillingData();
    </script>

</body>

</html>