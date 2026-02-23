<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Yearly Billing Report</title>
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
                <h4 class="fw-semibold mb-0">Billing Yearly Report</h4>
                <select id="yearSelector" class="form-select w-auto"></select>
            </div>

            <!-- SUMMARY -->
            <div class="row g-3 mb-4">

                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Billed</div>
                        <h5 id="totalBilled">₱0</h5>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Paid</div>
                        <h5 id="totalPaid">₱0</h5>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Pending Transactions</div>
                        <h5 id="pendingTransactions">0</h5>
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
                <canvas id="yearChart"></canvas>
            </div>

            <!-- MONTH SUMMARY CARDS -->
            <div class="row g-3" id="monthCards"></div>

        </div>
    </div>

    <script>
        const baseApi = "https://localhost:7212/journal/yearBillingReportSummary?year=";

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

                    // Summary
                    document.getElementById("yearLabel").innerText = data.year;
                    document.getElementById("totalBilled").innerText =
                        "₱" + data.total_billed.toLocaleString();
                    document.getElementById("totalPaid").innerText =
                        "₱" + data.total_paid.toLocaleString();
                    document.getElementById("pendingTransactions").innerText =
                        data.total_pending_transaction;

                    const labels = [];
                    const totals = [];
                    const container = document.getElementById("monthCards");
                    container.innerHTML = "";

                    const maxVal = Math.max(
                        ...data.monthsBilling.map(m => m.total_billed)
                    );

                    // Build month cards
                    data.monthsBilling.forEach(m => {

                        labels.push(monthNames[m.month - 1]);
                        totals.push(m.total_billed);

                        const percent = ((m.total_billed / maxVal) * 100).toFixed(0);

                        container.innerHTML += `
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="card p-3 month-card">
                                    <h6 class="fw-semibold">${monthNames[m.month - 1]}</h6>

                                    <div class="small-text">Total Billed</div>
                                    <div class="fw-semibold">₱${m.total_billed.toLocaleString()}</div>

                                    <div class="small-text mt-1">Paid</div>
                                    <div>₱${m.total_paid.toLocaleString()}</div>

                                    <div class="small-text mt-1">Pending Tx</div>
                                    <div>${m.total_pending_transaction}</div>

                                    <div class="small-text mt-1">Pending Amount</div>
                                    <div>₱${m.total_pending_amount.toLocaleString()}</div>

                                    <div class="progress mt-2" style="height:6px">
                                        <div class="progress-bar bg-primary" style="width:${percent}%"></div>
                                    </div>

                                    <div class="d-flex justify-content-end mt-3">
                                        <a href="/backend/report_and_analytics/month_billing_report.php?month=${m.month}&year=${data.year}"
                                        class="btn btn-sm btn-outline-primary">View</a>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    // YEARLY CHART
                    if (yearChart) yearChart.destroy();

                    yearChart = new Chart(document.getElementById("yearChart"), {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                label: "Total Billed",
                                data: totals,
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
                                        callback: v => "₱" + v.toLocaleString()
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