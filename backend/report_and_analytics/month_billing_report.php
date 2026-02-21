<?php include 'header.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Billing Monthly Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f7f7f7;
        }

        .small-text {
            font-size: .85rem;
            color: #6c757d;
        }

        .insight-box {
            background: #ffffff;
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <!-- Sidebar -->
        <?php include 'sidebar.php' ?>

        <div class="container py-4">

            <!-- HEADER -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-semibold mb-0">Billing Monthly Report</h4>
            </div>

            <h5 id="headerDate" class="text-secondary mb-4"></h5>

            <!-- SUMMARY CARDS -->
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
                        <div class="small-text">Pending Amount</div>
                        <h5 id="pendingAmount">₱0</h5>
                    </div>
                </div>

            </div>

            <!-- CHART -->
            <div class="card p-4 mb-4" style="height:320px;">
                <canvas id="billingChart"></canvas>
            </div>

            <!-- INSIGHTS -->
            <div class="card p-4 insight-box">
                <h6 class="fw-semibold mb-3">Billing Insights</h6>
                <div id="insightContent" class="small-text"></div>
            </div>

        </div>

        <script>
            // API base
            const apiBase = "https://bsis-03.keikaizen.xyz/journal/getMonthBillingReport";

            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            let billingChart;

            // Read month/year from URL
            const params = new URLSearchParams(window.location.search);
            const month = parseInt(params.get("month"));
            const year = parseInt(params.get("year"));

            // Validate parameters
            if (!month || !year) {
                document.body.innerHTML =
                    "<h3 class='text-center mt-5 text-danger'>Invalid month or year in URL.</h3>";
                throw new Error("Missing URL parameters");
            }

            // Set header
            document.getElementById("headerDate").innerText =
                `${monthNames[month - 1]} ${year}`;

            // Load report
            loadReport();

            function loadReport() {

                fetch(`${apiBase}?month=${month}&year=${year}`)
                    .then(res => res.json())
                    .then(data => {

                        // SUMMARY
                        document.getElementById("totalBilled").innerText =
                            "₱" + data.total_billed.toLocaleString();

                        document.getElementById("totalPaid").innerText =
                            "₱" + data.total_paid.toLocaleString();

                        document.getElementById("pendingTransactions").innerText =
                            data.total_pending_transaction;

                        document.getElementById("pendingAmount").innerText =
                            "₱" + data.total_pending_amount.toLocaleString();

                        // CHART
                        if (billingChart) billingChart.destroy();

                        billingChart = new Chart(document.getElementById("billingChart"), {
                            type: "bar",
                            data: {
                                labels: [
                                    "Total Billed",
                                    "Total Paid",
                                    "OOP Collected",
                                    "Insurance Covered",
                                    "Pending Amount"
                                ],
                                datasets: [{
                                    label: "₱ Amount",
                                    data: [
                                        data.total_billed,
                                        data.total_paid,
                                        data.total_oop_collected,
                                        data.total_insurance_covered,
                                        data.total_pending_amount
                                    ],
                                    backgroundColor: [
                                        "#0d6efd",
                                        "#198754",
                                        "#fd7e14",
                                        "#6f42c1",
                                        "#dc3545"
                                    ]
                                }]
                            },
                            options: {
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        ticks: {
                                            callback: v => "₱" + v.toLocaleString()
                                        }
                                    }
                                }
                            }
                        });

                        // INSIGHTS
                        const paidRate = ((data.total_paid / data.total_billed) * 100).toFixed(1);
                        const pendingRate =
                            ((data.total_pending_amount / data.total_billed) * 100).toFixed(1);

                        document.getElementById("insightContent").innerHTML = `
                            • Total billed: <strong>₱${data.total_billed.toLocaleString()}</strong><br>
                            • Paid coverage: <strong>${paidRate}%</strong><br>
                            • Pending amount: <strong>₱${data.total_pending_amount.toLocaleString()} (${pendingRate}%)</strong><br>
                            • OOP collected: <strong>₱${data.total_oop_collected.toLocaleString()}</strong><br>
                            • Insurance covered: <strong>₱${data.total_insurance_covered.toLocaleString()}</strong><br>
                        `;
                    });
            }
        </script>
    </div>
</body>

</html>