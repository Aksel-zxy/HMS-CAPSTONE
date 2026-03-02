<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Insurance Claim Report</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <!-- Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

</head>

<body class="bg-light">

    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <h2 class="fw-bold text-primary mb-4">📅 Monthly Insurance Claim Report</h2>

            <!-- ===================== MONTH & YEAR ===================== -->
            <div class="alert alert-info">
                <strong>Viewing Monthly Report:</strong>
                <span id="monthLabel"></span>
            </div>

            <!-- ===================== KPI GRID ===================== -->
            <div class="row g-3" id="kpiGrid">
                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiTotalClaims" class="fw-bold text-primary">0</h4>
                        <p>Total Claims</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiApproved" class="fw-bold text-success">0</h4>
                        <p>Approved Claims</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiDenied" class="fw-bold text-danger">0</h4>
                        <p>Denied Claims</p>
                    </div>
                </div>

                <!-- Financial -->
                <div class="col-md-6">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiApprovedAmount" class="fw-bold text-success">₱0</h4>
                        <p>Total Approved Amount</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-3 text-center shadow-sm">
                        <h4 id="kpiDeniedAmount" class="fw-bold text-danger">₱0</h4>
                        <p>Total Denied Amount</p>
                    </div>
                </div>
            </div>

            <!-- ===================== CHART SECTION ===================== -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card shadow-sm p-3">
                        <h6 class="fw-semibold">Claim Status Distribution</h6>
                        <canvas id="claimPieChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm p-3">
                        <h6 class="fw-semibold">Financial Breakdown</h6>
                        <canvas id="financialBarChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- ===================== INSIGHTS ===================== -->
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="fw-bold">📘 Insights</h5>
                    <div id="insightsContent"></div>
                </div>
            </div>

            <!-- ===================== AI SUGGESTIONS ===================== -->
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="fw-bold">🤖 AI Recommendations</h5>
                    <div id="recommendContent"></div>
                </div>
            </div>

            <!-- ===================== EXPORT BUTTONS ===================== -->
            <div class="text-end mt-3">
                <button class="btn btn-success me-2" onclick="exportExcel()">Export Excel</button>
                <button class="btn btn-danger" onclick="exportPDF()">Export PDF</button>
            </div>

        </div>
    </div>


    <script>
        // Get month & year from URL
        const urlParams = new URLSearchParams(window.location.search);
        const month = urlParams.get("month");
        const year = urlParams.get("year");

        const monthNames = ["January", "February", "March", "April", "May", "June", "July",
            "August", "September", "October", "November", "December"
        ];

        document.getElementById("monthLabel").innerText =
            `${monthNames[month - 1]} ${year}`;

        // Fetch API
        async function loadReport() {
            const url = `https://bsis-03.keikaizen.xyz/insurance/getMonthInsuranceReport?month=${month}&year=${year}`;
            const res = await fetch(url);
            const data = await res.json();

            renderKPI(data);
            renderCharts(data);
            renderInsights(data);
            renderAI(data);
        }

        // ======================= KPIs =======================
        function renderKPI(data) {
            document.getElementById("kpiTotalClaims").innerText = data.totalClaims;
            document.getElementById("kpiApproved").innerText = data.totalApprovedClaims;
            document.getElementById("kpiDenied").innerText = data.totalDeniedClaims;

            document.getElementById("kpiApprovedAmount").innerText = "₱" + data.totalApprovedAmount.toLocaleString();
            document.getElementById("kpiDeniedAmount").innerText = "₱" + data.totalDeniedAmount.toLocaleString();
        }

        // ======================= CHARTS =======================
        let pieChart, barChart;

        function renderCharts(data) {
            const ctx1 = document.getElementById("claimPieChart");
            const ctx2 = document.getElementById("financialBarChart");

            if (pieChart) pieChart.destroy();
            if (barChart) barChart.destroy();

            // PIE CHART
            pieChart = new Chart(ctx1, {
                type: "doughnut",
                data: {
                    labels: ["Approved", "Denied"],
                    datasets: [{
                        data: [data.totalApprovedClaims, data.totalDeniedClaims],
                        backgroundColor: ["green", "red"]
                    }]
                },
                options: {
                    cutout: "55%"
                }
            });

            // BAR CHART
            barChart = new Chart(ctx2, {
                type: "bar",
                data: {
                    labels: ["Approved Amount", "Denied Amount"],
                    datasets: [{
                        data: [data.totalApprovedAmount, data.totalDeniedAmount],
                        backgroundColor: ["green", "red"]
                    }]
                }
            });
        }

        // ======================= INSIGHTS =======================
        function renderInsights(data) {
            const denialRate = Math.round((data.totalDeniedClaims / data.totalClaims) * 100);
            const approvalRate = 100 - denialRate;

            document.getElementById("insightsContent").innerHTML = `
                <p>✔ Approval Rate: <b>${approvalRate}%</b></p>
                <p>✔ Denial Rate: <b>${denialRate}%</b></p>
                <p>✔ Approved Amount: <b>₱${data.totalApprovedAmount.toLocaleString()}</b></p>
                <p>✔ Denied Amount: <b>₱${data.totalDeniedAmount.toLocaleString()}</b></p>
                <p>✔ Total Claims Filed: <b>${data.totalClaims}</b></p>
            `;
        }

        // ======================= AI RECOMMENDATIONS =======================
        function renderAI(data) {
            const denialRate = Math.round((data.totalDeniedClaims / data.totalClaims) * 100);
            let rec = `<p><b>🤖 Automated System Analysis</b></p>`;

            if (denialRate > 50) {
                rec += `
                    <p>⚠ <b>High Denial Rate</b>: ${denialRate}%.  
                    Recommend reviewing documentation issues, insurance provider patterns,  
                    and claim justification notes.</p>
                `;
            } else if (denialRate < 20) {
                rec += `
                    <p>✅ <b>Healthy Approval Rate</b>: ${100 - denialRate}%.  
                    Current processes are working effectively.</p>
                `;
            }

            if (data.totalDeniedAmount > data.totalApprovedAmount) {
                rec += `
                    <p>💰 High denied claim amount detected.  
                    Consider auditing claims that exceed ₱10,000 for accuracy and compliance.</p>
                `;
            }

            rec += `
                <p>📌 Recommendation: Build monthly variance tracking to spot sudden spikes  
                in denials or insurer behavior changes.</p>
            `;

            document.getElementById("recommendContent").innerHTML = rec;
        }

        // ======================= EXPORT =======================
        function exportExcel() {
            const wb = XLSX.utils.book_new();
            const wsData = [
                ["Metric", "Value"],
                ["Total Claims", document.getElementById("kpiTotalClaims").innerText],
                ["Approved Claims", document.getElementById("kpiApproved").innerText],
                ["Denied Claims", document.getElementById("kpiDenied").innerText],
                ["Approved Amount", document.getElementById("kpiApprovedAmount").innerText],
                ["Denied Amount", document.getElementById("kpiDeniedAmount").innerText]
            ];

            const ws = XLSX.utils.aoa_to_sheet(wsData);
            XLSX.utils.book_append_sheet(wb, ws, "Monthly Insurance");

            XLSX.writeFile(wb, "Monthly_Insurance_Report.xlsx");
        }

        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();
            doc.text("Monthly Insurance Report", 14, 15);

            doc.autoTable({
                head: [
                    ["Metric", "Value"]
                ],
                body: [
                    ["Total Claims", document.getElementById("kpiTotalClaims").innerText],
                    ["Approved Claims", document.getElementById("kpiApproved").innerText],
                    ["Denied Claims", document.getElementById("kpiDenied").innerText],
                    ["Approved Amount", document.getElementById("kpiApprovedAmount").innerText],
                    ["Denied Amount", document.getElementById("kpiDeniedAmount").innerText]
                ],
                startY: 20
            });

            doc.save("Monthly_Insurance_Report.pdf");
        }

        // Load the report on page start
        loadReport();
    </script>

</body>

</html>