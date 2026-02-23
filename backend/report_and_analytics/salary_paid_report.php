<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payroll Monthly Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <!-- HEADER -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-semibold mb-0">Payroll Monthly Report</h4>
                <h5 id="headerDate" class="mb-0 text-secondary"></h5>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Employees</div>
                        <h5 id="totalEmployees">0</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Gross Pay</div>
                        <h5 id="totalGrossPay">₱0</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Deductions</div>
                        <h5 id="totalDeductions">₱0</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Net Pay</div>
                        <h5 id="totalNetPay">₱0</h5>
                    </div>
                </div>
            </div>

            <!-- CHART -->
            <div class="card p-4 mb-4" style="height:320px;">
                <canvas id="payrollChart"></canvas>
            </div>

            <!-- INSIGHTS -->
            <div class="card p-4 insight-box">
                <h6 class="fw-semibold mb-3">Payroll Insights</h6>
                <div id="insightContent" class="small-text"></div>
            </div>

        </div>
    </div>

    <script>
        const apiBase = "https://localhost:7212/payroll/getMonthPayrollSummary?month=";

        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        let payrollChart;

        // 🔥 Get MONTH & YEAR from URL
        const urlParams = new URLSearchParams(window.location.search);
        const month = parseInt(urlParams.get("month")) || (new Date().getMonth() + 1);
        const year = parseInt(urlParams.get("year")) || new Date().getFullYear();

        // Display header month/year
        document.getElementById("headerDate").innerText = `${monthNames[month - 1]} ${year}`;

        // Auto-load on page load
        loadReport();

        function loadReport() {
            fetch(apiBase + month + "&year=" + year)
                .then(res => res.json())
                .then(data => {
                    // SUMMARY CARDS
                    document.getElementById("totalEmployees").innerText = data.totalEmployees;
                    document.getElementById("totalGrossPay").innerText = "₱" + data.totalGrossPay.toLocaleString();
                    document.getElementById("totalDeductions").innerText = "₱" + data.totalDeductions.toLocaleString();
                    document.getElementById("totalNetPay").innerText = "₱" + data.totalNetPay.toLocaleString();

                    // DESTROY OLD CHART IF EXISTS
                    if (payrollChart) payrollChart.destroy();

                    // CHART DATA
                    const labels = [];
                    const gross = [];
                    const deductions = [];
                    const net = [];

                    data.monthsRecords.forEach(rec => {
                        labels.push(monthNames[rec.month - 1] + " " + rec.year);
                        gross.push(rec.total_gross_pay);
                        deductions.push(rec.total_deductions);
                        net.push(rec.total_net_pay);
                    });

                    // RENDER CHART
                    payrollChart = new Chart(document.getElementById("payrollChart"), {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                    label: "Gross Pay",
                                    data: gross,
                                    backgroundColor: "#87cefa"
                                },
                                {
                                    label: "Deductions",
                                    data: deductions,
                                    backgroundColor: "#fd7e14"
                                },
                                {
                                    label: "Net Pay",
                                    data: net,
                                    backgroundColor: "#90ee90"
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: "top"
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true
                                },
                                y: {
                                    stacked: true,
                                    ticks: {
                                        callback: (v) => "₱" + v.toLocaleString()
                                    }
                                }
                            }
                        }
                    });

                    // INSIGHTS
                    const deductionRate = ((data.totalDeductions / data.totalGrossPay) * 100).toFixed(1);
                    const avgNet = (data.totalNetPay / data.totalEmployees).toFixed(2);

                    let insights = `
                        • Payroll covers <strong>${data.totalEmployees}</strong> employees.<br>
                        • Deductions are <strong>${deductionRate}%</strong> of gross.<br>
                        • Avg net per employee: <strong>₱${Number(avgNet).toLocaleString()}</strong>.<br>
                        • Net payroll released: <strong>₱${data.totalNetPay.toLocaleString()}</strong>.
                    `;

                    // Previous month comparison
                    const prev = data.monthsRecords.find(r =>
                        (r.month === month - 1 && r.year === year) ||
                        (month === 1 && r.month === 12 && r.year === year - 1)
                    );

                    if (prev) {
                        const diff = data.totalNetPay - prev.total_net_pay;
                        const pct = ((diff / prev.total_net_pay) * 100).toFixed(2);
                        const arrow = diff >= 0 ? "▲" : "▼";

                        insights += `
                            <br>• Compared to previous month: 
                            <strong>${arrow} ₱${Math.abs(diff).toLocaleString()} (${pct}%)</strong>.
                        `;
                    }

                    document.getElementById("insightContent").innerHTML = insights;
                });
        }
    </script>

</body>

</html>