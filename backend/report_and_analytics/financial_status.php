<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Hospital Financial Report</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f5f6fa;
        }

        .card {
            border-radius: 12px;
        }

        .summary-card {
            text-align: center;
            padding: 20px;
        }

        .summary-value {
            font-size: 28px;
            font-weight: bold;
        }

        .loss {
            color: red;
            font-weight: bold;
        }

        .profit {
            color: green;
            font-weight: bold;
        }

        .status-box {
            padding: 20px;
            border-radius: 12px;
            font-size: 24px;
            text-align: center;
            margin-bottom: 25px;
        }
    </style>
</head>

<body>

    <div class="container mt-4">

        <h2 class="mb-4 text-center">Hospital Financial Report (Monthly)</h2>

        <!-- Financial Status -->
        <div id="financialStatus" class="status-box shadow-sm"></div>

        <div class="row">
            <div class="col-md-4">
                <div class="card summary-card shadow-sm">
                    <h5>Total Revenue</h5>
                    <div class="summary-value">₱146,285.44</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card summary-card shadow-sm">
                    <h5>Total Expenses</h5>
                    <div class="summary-value">₱475,000.00</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card summary-card shadow-sm">
                    <h5>Net Profit</h5>
                    <div id="profitDisplay" class="summary-value"></div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <h4>Financial Overview Charts</h4>

        <div class="row mt-4">
            <div class="col-md-6">
                <canvas id="revenueVsExpenses"></canvas>
            </div>

            <div class="col-md-6">
                <canvas id="expenseBreakdown"></canvas>
            </div>
        </div>

        <hr class="my-4">

        <h4>Statistical Summary</h4>
        <ul>
            <li><strong>Total Revenue:</strong> ₱146,285.44</li>
            <li><strong>Total Expenses:</strong> ₱475,000.00</li>
            <li><strong>Net Loss:</strong> -₱328,714.56</li>
            <li><strong>Revenue-to-Expense Ratio:</strong> 30%</li>
            <li><strong>ICU beds (6) contribute highest operating cost.</strong></li>
            <li><strong>4 beds under maintenance increased cost by ₱20,000.</strong></li>
        </ul>

    </div>

    <script>
        // Data
        const revenue = 146285.44;
        const expenses = 475000;
        const profit = revenue - expenses;

        // Display Profit and Status
        const profitDisplay = document.getElementById("profitDisplay");
        const statusBox = document.getElementById("financialStatus");

        if (profit >= 0) {
            profitDisplay.classList.add("profit");
            profitDisplay.textContent = "₱" + profit.toLocaleString(undefined, {
                minimumFractionDigits: 2
            });
            statusBox.classList.add("profit");
            statusBox.textContent = "STATUS: PROFITABLE ✔ The hospital is earning money.";
            statusBox.style.background = "#d4edda";
            statusBox.style.color = "#155724";
        } else {
            profitDisplay.classList.add("loss");
            profitDisplay.textContent = "-₱" + Math.abs(profit).toLocaleString(undefined, {
                minimumFractionDigits: 2
            });
            statusBox.classList.add("loss");
            statusBox.textContent = "STATUS: LOSING MONEY ✖ The hospital is operating at a loss.";
            statusBox.style.background = "#f8d7da";
            statusBox.style.color = "#721c24";
        }

        // Revenue vs Expenses Chart
        new Chart(document.getElementById('revenueVsExpenses'), {
            type: 'bar',
            data: {
                labels: ['Revenue', 'Expenses', 'Net Profit'],
                datasets: [{
                    label: 'Amount (PHP)',
                    data: [revenue, expenses, profit],
                    backgroundColor: ['#28a745', '#dc3545', '#007bff']
                }]
            }
        });

        // Expense Breakdown
        new Chart(document.getElementById('expenseBreakdown'), {
            type: 'pie',
            data: {
                labels: [
                    'Staff', 'Utilities', 'Medical Supplies',
                    'Maintenance', 'Admin & Office', 'Depreciation'
                ],
                datasets: [{
                    data: [308000, 30000, 17000, 20000, 40000, 60000],
                    backgroundColor: [
                        '#ff6384', '#36a2eb', '#ffcd56',
                        '#4bc0c0', '#9966ff', '#ff9f40'
                    ]
                }]
            }
        });
    </script>

</body>

</html>