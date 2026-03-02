<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Payroll Comparison</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            font-family: "Poppins", sans-serif;
        }

        .stat-card {
            border-radius: 16px;
            border: 1px solid #ddd;
            background: #fff;
            padding: 20px;
        }

        .diff-up {
            color: #198754;
            font-weight: 600;
        }

        .diff-down {
            color: #dc3545;
            font-weight: 600;
        }

        .diff-neutral {
            color: #6c757d;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <div class="container my-5">

            <h3 class="fw-bold mb-4 text-center">💰 Monthly Payroll Comparison</h3>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Base Month</label>
                    <input type="month" id="baseMonthInput" class="form-control" value="2025-12">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Partner Month</label>
                    <input type="month" id="partnerMonthInput" class="form-control" value="2025-09">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-dark w-100" onclick="loadComparison()">Compare</button>
                </div>
            </div>

            <!-- Comparison Table -->
            <div class="stat-card">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Metric</th>
                                <th id="baseMonthLabel">Base Month</th>
                                <th id="partnerMonthLabel">Partner Month</th>
                                <th>Difference (%)</th>
                            </tr>
                        </thead>
                        <tbody id="comparisonBody">
                            <tr>
                                <td colspan="4" class="text-muted">No data loaded</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary -->
            <div class="stat-card mt-4">
                <h5 class="fw-bold mb-2">📌 Summary</h5>
                <div id="comparisonSummary" class="text-muted">
                    No summary available.
                </div>
            </div>

        </div>

        <script>
            function percentDiff(current, previous) {
                if (previous === 0) {
                    return {
                        text: "N/A",
                        class: "diff-neutral",
                        value: 0
                    };
                }

                const diff = ((current - previous) / previous) * 100;

                return {
                    text: diff > 0 ? `▲ ${diff.toFixed(2)}%` : diff < 0 ? `▼ ${Math.abs(diff).toFixed(2)}%` : "0%",
                    class: diff > 0 ? "diff-up" : diff < 0 ? "diff-down" : "diff-neutral",
                    value: diff
                };
            }

            function formatCurrency(value) {
                return "₱ " + Number(value).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function buildSummary(d) {

                const empDiff = percentDiff(d.baseTotalEmployees, d.comparedTotalEmployees);
                const grossDiff = percentDiff(d.baseTotalGrossPay, d.comparedTotalGrossPay);
                const deductionDiff = percentDiff(d.baseTotalDeductions, d.comparedTotalDeductions);
                const netDiff = percentDiff(d.baseTotalNetPay, d.comparedTotalNetPay);

                let summaryHtml = `
        <strong>${d.baseMonth}/${d.baseYear} vs ${d.comparedMonth}/${d.comparedYear}</strong><br><br>
    `;

                summaryHtml += `
        • Employee count changed by 
        <span class="${empDiff.class}">${empDiff.value.toFixed(2)}%</span><br>
    `;

                summaryHtml += `
        • Gross Pay changed by 
        <span class="${grossDiff.class}">${grossDiff.value.toFixed(2)}%</span><br>
    `;

                summaryHtml += `
        • Deductions changed by 
        <span class="${deductionDiff.class}">${deductionDiff.value.toFixed(2)}%</span><br>
    `;

                summaryHtml += `
        • Net Pay changed by 
        <span class="${netDiff.class}">${netDiff.value.toFixed(2)}%</span>
    `;

                document.getElementById("comparisonSummary").innerHTML = summaryHtml;
            }

            async function loadComparison() {

                const baseVal = document.getElementById("baseMonthInput").value;
                const partnerVal = document.getElementById("partnerMonthInput").value;

                const [baseYear, baseMonth] = baseVal.split("-").map(Number);
                const [partnerYear, partnerMonth] = partnerVal.split("-").map(Number);

                document.getElementById("baseMonthLabel").innerText = `${baseMonth}/${baseYear}`;
                document.getElementById("partnerMonthLabel").innerText = `${partnerMonth}/${partnerYear}`;

                const tbody = document.getElementById("comparisonBody");
                const summaryDiv = document.getElementById("comparisonSummary");

                tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;
                summaryDiv.innerHTML = "";

                try {

                    const res = await fetch(
                        `https://bsis-03.keikaizen.xyz/payroll/monthPayrollComparisonEndpoint?month=${baseMonth}&year=${baseYear}&partnerMonth=${partnerMonth}&partnerYear=${partnerYear}`
                    );

                    // 🔥 HANDLE SAME MONTH ERROR (400)
                    if (res.status === 400) {

                        const errorMessage = await res.text();

                        tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-danger fw-semibold">
                        ⚠ ${errorMessage}
                    </td>
                </tr>
            `;

                        summaryDiv.innerHTML = `
                <div class="alert alert-danger mb-0">
                    ${errorMessage}
                </div>
            `;

                        return;
                    }

                    if (!res.ok) throw new Error("Failed to fetch data");

                    const d = await res.json();
                    tbody.innerHTML = "";

                    // Employees
                    const empDiff = percentDiff(d.baseTotalEmployees, d.comparedTotalEmployees);
                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Total Employees</td>
                <td>${d.baseTotalEmployees}</td>
                <td>${d.comparedTotalEmployees}</td>
                <td class="${empDiff.class}">${empDiff.text}</td>
            </tr>
        `;

                    // Gross Pay
                    const grossDiff = percentDiff(d.baseTotalGrossPay, d.comparedTotalGrossPay);
                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Total Gross Pay</td>
                <td>${formatCurrency(d.baseTotalGrossPay)}</td>
                <td>${formatCurrency(d.comparedTotalGrossPay)}</td>
                <td class="${grossDiff.class}">${grossDiff.text}</td>
            </tr>
        `;

                    // Deductions
                    const deductionDiff = percentDiff(d.baseTotalDeductions, d.comparedTotalDeductions);
                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Total Deductions</td>
                <td>${formatCurrency(d.baseTotalDeductions)}</td>
                <td>${formatCurrency(d.comparedTotalDeductions)}</td>
                <td class="${deductionDiff.class}">${deductionDiff.text}</td>
            </tr>
        `;

                    // Net Pay
                    const netDiff = percentDiff(d.baseTotalNetPay, d.comparedTotalNetPay);
                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Total Net Pay</td>
                <td>${formatCurrency(d.baseTotalNetPay)}</td>
                <td>${formatCurrency(d.comparedTotalNetPay)}</td>
                <td class="${netDiff.class}">${netDiff.text}</td>
            </tr>
        `;

                    buildSummary(d);

                } catch (err) {

                    tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-danger fw-semibold">
                    ⚠ Something went wrong while loading payroll comparison.
                </td>
            </tr>
        `;

                    summaryDiv.innerHTML = `
            <div class="alert alert-danger mb-0">
                Unable to generate summary.
            </div>
        `;
                }
            }

            // Auto load default comparison
            loadComparison();
        </script>

    </div>
</body>

</html>