<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Yearly Hospital Payroll Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
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

        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <!-- HEADER + YEAR SELECT -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-semibold mb-0">Hospital Payroll Yearly Report</h4>
                <select id="yearSelector" class="form-select w-auto"></select>
            </div>

            <!-- SUMMARY -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Employees (December)</div>
                        <h5 id="totalEmployees">0</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Year Gross Pay</div>
                        <h5 id="totalGross">₱0</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Year Deductions</div>
                        <h5 id="totalDeductions">₱0</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Year Net Pay</div>
                        <h5 id="totalNet">₱0</h5>
                    </div>
                </div>
            </div>

            <!-- YEARLY NET PAY CHART -->
            <div class="card p-4 mb-4" style="height:320px">
                <canvas id="yearChart"></canvas>
            </div>

            <!-- MONTH CARDS -->
            <div class="row g-3" id="monthCards"></div>

        </div>
    </div>

    <script>
        const baseApi = "https://localhost:7212/payroll/yearHospitalPayrollReport?year=";

        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        let yearChart;

        const yearSelect = document.getElementById("yearSelector");
        const currentYear = new Date().getFullYear();

        for (let y = currentYear; y >= currentYear - 5; y--) {
            yearSelect.innerHTML += `<option value="${y}">${y}</option>`;
        }

        yearSelect.addEventListener("change", () => loadReport(yearSelect.value));

        loadReport(yearSelect.value);

        function formatCurrency(value) {
            return "₱" + Number(value).toLocaleString();
        }

        function loadReport(year) {

            fetch(baseApi + year)
                .then(res => res.json())
                .then(data => {

                    // SUMMARY
                    document.getElementById("totalEmployees").innerText = data.total_employees;
                    document.getElementById("totalGross").innerText = formatCurrency(data.year_total_gross_pay);
                    document.getElementById("totalDeductions").innerText = formatCurrency(data.year_total_deductions);
                    document.getElementById("totalNet").innerText = formatCurrency(data.year_total_net_pay);

                    const labels = [];
                    const netPays = [];

                    const container = document.getElementById("monthCards");
                    container.innerHTML = "";

                    const maxNet = Math.max(...data.monthsPayroll.map(m => m.total_net_pay));

                    data.monthsPayroll.forEach(m => {

                        labels.push(monthNames[m.month - 1]);
                        netPays.push(m.total_net_pay);

                        const percent = ((m.total_net_pay / maxNet) * 100).toFixed(0);

                        container.innerHTML += `
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="card p-3 month-card">
                                <h6 class="fw-semibold">${monthNames[m.month - 1]}</h6>
                                <div class="small-text">Employees</div>
                                <div>${m.total_employees}</div>

                                <div class="small-text mt-2">Gross</div>
                                <div class="fw-semibold">${formatCurrency(m.total_gross_pay)}</div>

                                <div class="small-text mt-2">Deductions</div>
                                <div>${formatCurrency(m.total_deductions)}</div>

                                <div class="small-text mt-2">Net</div>
                                <div class="fw-semibold">${formatCurrency(m.total_net_pay)}</div>

                                <div class="progress mt-3" style="height:6px">
                                    <div class="progress-bar bg-success" style="width:${percent}%"></div>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <a href="/backend/report_and_analytics/salary_paid_report.php?month=${m.month}&year=${data.year}"
                                       class="btn btn-sm btn-outline-primary">View</a>
                                </div>
                            </div>
                        </div>
                        `;
                    });

                    // DESTROY OLD CHART
                    if (yearChart) yearChart.destroy();

                    yearChart = new Chart(document.getElementById("yearChart"), {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                label: "Net Pay",
                                data: netPays,
                                backgroundColor: "#198754"
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