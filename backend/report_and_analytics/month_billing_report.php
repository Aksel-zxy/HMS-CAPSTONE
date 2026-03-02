<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Billing Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jsPDF & AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <!-- SheetJS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>

<style>
    #sourceChart {
        width: 300px !important;
        height: 300px !important;
        margin: 0 auto;
        display: block;
    }
</style>

<body class="bg-light">
    <div class="d-flex">

        <?php include 'sidebar.php'; ?>

        <div class="container py-4">
            <?php
            $month = $_GET['month'] ?? 1;
            $year = $_GET['year'] ?? 2025;

            $monthNames = [
                "January",
                "February",
                "March",
                "April",
                "May",
                "June",
                "July",
                "August",
                "September",
                "October",
                "November",
                "December"
            ];
            $monthLabel = $monthNames[$month - 1];
            ?>

            <h2 class="mb-4 fw-bold text-primary">💳 Billing Report — <?= $monthLabel . " " . $year ?></h2>

            <!-- ======================= SUMMARY CARDS ======================= -->
            <div class="row g-3 mb-4" id="summaryCards">

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-primary" id="sumBilled">₱0</h4>
                        <p class="m-0">Total Billed</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-success" id="sumPaid">₱0</h4>
                        <p class="m-0">Total Paid</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-danger" id="sumPendingTrans">0</h4>
                        <p class="m-0">Pending Txns</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-warning" id="sumOOP">₱0</h4>
                        <p class="m-0">OOP Collected</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-info" id="sumInsurance">₱0</h4>
                        <p class="m-0">Insurance Cov.</p>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm text-center p-3">
                        <h4 class="fw-bold text-secondary" id="sumPendingAmt">₱0</h4>
                        <p class="m-0">Pending Amount</p>
                    </div>
                </div>

            </div>

            <!-- ======================= CHART 1 ======================= -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Billing vs Paid</h5>
                    <canvas id="billingChart" height="120"></canvas>
                </div>
            </div>

            <!-- ======================= CHART 2 ======================= -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">OOP vs Insurance Coverage</h5>
                    <canvas id="sourceChart" height="120"></canvas>
                </div>
            </div>

            <!-- ======================= AI INSIGHTS ======================= -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">🤖 AI Insights & Recommendations</h5>
                    <div id="insightsContent" class="p-2"></div>
                </div>
            </div>

            <!-- ======================= EXPORT BUTTONS ======================= -->
            <div class="text-end mb-4">
                <button class="btn btn-success me-2" onclick="exportExcel()">Export to Excel</button>
                <button class="btn btn-danger" onclick="exportPDF()">Export to PDF</button>
            </div>

        </div>

    </div>

    <script>
        const month = <?= $month ?>;
        const year = <?= $year ?>;

        const endpoint = `https://localhost:7212/journal/getMonthBillingReport?month=${month}&year=${year}`;

        let chart1 = null;
        let chart2 = null;

        async function loadBilling() {
            const res = await fetch(endpoint);
            const data = await res.json();

            // Populate Summary Cards
            document.getElementById("sumBilled").innerText = "₱" + data.total_billed.toLocaleString();
            document.getElementById("sumPaid").innerText = "₱" + data.total_paid.toLocaleString();
            document.getElementById("sumPendingTrans").innerText = data.total_pending_transaction;
            document.getElementById("sumOOP").innerText = "₱" + data.total_oop_collected.toLocaleString();
            document.getElementById("sumInsurance").innerText = "₱" + data.total_insurance_covered.toLocaleString();
            document.getElementById("sumPendingAmt").innerText = "₱" + data.total_pending_amount.toLocaleString();

            // ==================== BILLING VS PAID CHART ====================
            const ctx1 = document.getElementById("billingChart").getContext("2d");
            if (chart1) chart1.destroy();

            chart1 = new Chart(ctx1, {
                type: "bar",
                data: {
                    labels: ["Total Billed", "Total Paid"],
                    datasets: [{
                        label: "Amount",
                        data: [data.total_billed, data.total_paid],
                        backgroundColor: ["steelblue", "mediumseagreen"]
                    }]
                }
            });

            // ==================== OOP vs INSURANCE (BALANCED DOUGHNUT) ====================
            const ctx2 = document.getElementById("sourceChart").getContext("2d");
            if (chart2) chart2.destroy();

            chart2 = new Chart(ctx2, {
                type: "doughnut",
                data: {
                    labels: ["OOP Collected", "Insurance Covered"],
                    datasets: [{
                        data: [data.total_oop_collected, data.total_insurance_covered],
                        backgroundColor: ["orange", "purple"]
                    }]
                },
                options: {
                    responsive: false,
                    maintainAspectRatio: false,
                    cutout: "60%", // regular doughnut thickness
                    plugins: {
                        legend: {
                            position: "bottom",
                            labels: {
                                boxWidth: 15
                            }
                        }
                    }
                }
            });

            // ==================== AI INSIGHTS ====================
            document.getElementById("insightsContent").innerHTML = generateInsights(data);
        }

        function generateInsights(d) {
            let html = "";

            // Billing-to-Paid ratio
            const collectionRate = Math.round((d.total_paid / d.total_billed) * 100);

            html += `<p>✔ Collection rate for this month: <b>${collectionRate}%</b>.</p>`;

            if (collectionRate < 80) {
                html += `<p>⚠ Low collection efficiency detected. Review billing follow-up processes.</p>`;
            } else {
                html += `<p>✔ Billing team performed efficiently this month.</p>`;
            }

            if (d.total_pending_transaction > 2) {
                html += `<p>⚠ High pending transactions: <b>${d.total_pending_transaction}</b>. Identify bottlenecks and delays.</p>`;
            }

            if (d.total_pending_amount > 5000) {
                html += `<p>⚠ Large pending amount (₱${d.total_pending_amount.toLocaleString()}). This impacts cash flow.</p>`;
            }

            html += `
        <h6 class="fw-bold mt-3">Recommendations:</h6>
        <ul>
            <li>Improve tracking of unpaid patient balances.</li>
            <li>Follow-up pending insurance claims before they expire.</li>
            <li>Analyze frequent pending cases for root cause (doctor, service type, insurance).</li>
            <li>Consider auto-notifications for unpaid bills at discharge.</li>
        </ul>
        `;

            return html;
        }

        // ==================== EXPORT FUNCTIONS ====================
        function exportExcel() {
            const sheet = XLSX.utils.json_to_sheet([{
                Total_Billed: document.getElementById("sumBilled").innerText,
                Total_Paid: document.getElementById("sumPaid").innerText,
                Pending_Transactions: document.getElementById("sumPendingTrans").innerText,
                OOP_Collected: document.getElementById("sumOOP").innerText,
                Insurance_Covered: document.getElementById("sumInsurance").innerText,
                Pending_Amount: document.getElementById("sumPendingAmt").innerText
            }]);

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, sheet, "Billing Report");
            XLSX.writeFile(wb, "Monthly_Billing_Report.xlsx");
        }

        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            doc.text("Monthly Billing Report", 14, 15);

            doc.autoTable({
                head: [
                    ["Metric", "Value"]
                ],
                body: [
                    ["Total Billed", document.getElementById("sumBilled").innerText],
                    ["Total Paid", document.getElementById("sumPaid").innerText],
                    ["Pending Transactions", document.getElementById("sumPendingTrans").innerText],
                    ["Out-of-Pocket", document.getElementById("sumOOP").innerText],
                    ["Insurance Covered", document.getElementById("sumInsurance").innerText],
                    ["Pending Amount", document.getElementById("sumPendingAmt").innerText]
                ],
                startY: 20
            });

            doc.save("Monthly_Billing_Report.pdf");
        }

        loadBilling();
    </script>

</body>

</html>