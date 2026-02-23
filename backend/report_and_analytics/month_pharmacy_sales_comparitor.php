<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Pharmacy Sales Comparison</title>
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

            <h3 class="fw-bold mb-4 text-center">💊 Monthly Pharmacy Sales Comparison</h3>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">First Month</label>
                    <input type="month" id="firstMonthInput" class="form-control" value="2025-01">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Second Month</label>
                    <input type="month" id="secondMonthInput" class="form-control" value="2025-02">
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
                                <th id="firstMonthLabel">First Month</th>
                                <th id="secondMonthLabel">Second Month</th>
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
                if (previous === 0) return {
                    text: "N/A",
                    class: "diff-neutral",
                    value: 0
                };

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

            function buildSummary(data) {

                const salesDiff = percentDiff(data.partnerTotalSales, data.baseTotalSales);
                const transDiff = percentDiff(data.partnerTotalTransactions, data.baseTotalTransactions);

                let summaryHtml = `
            <strong>${data.baseMonth}/${data.baseYear} vs ${data.partnerMonth}/${data.partnerYear}</strong><br><br>
        `;

                summaryHtml += `
            • Total Transactions changed by 
            <span class="${transDiff.class}">
                ${transDiff.value.toFixed(2)}%
            </span><br>
        `;

                summaryHtml += `
            • Total Sales changed by 
            <span class="${salesDiff.class}">
                ${salesDiff.value.toFixed(2)}%
            </span><br><br>
        `;

                summaryHtml += `
            • Top Selling Item (${data.baseMonth}/${data.baseYear}): 
            <strong>${data.baseTopSellingItem}</strong><br>
            • Top Selling Item (${data.partnerMonth}/${data.partnerYear}): 
            <strong>${data.partnerTopSellingItem}</strong>
        `;

                document.getElementById("comparisonSummary").innerHTML = summaryHtml;
            }

            async function loadComparison() {

                const firstMonthVal = document.getElementById("firstMonthInput").value;
                const secondMonthVal = document.getElementById("secondMonthInput").value;

                const [firstYear, firstMonth] = firstMonthVal.split("-").map(Number);
                const [secondYear, secondMonth] = secondMonthVal.split("-").map(Number);

                document.getElementById("firstMonthLabel").innerText = `${firstMonth}/${firstYear}`;
                document.getElementById("secondMonthLabel").innerText = `${secondMonth}/${secondYear}`;

                const tbody = document.getElementById("comparisonBody");
                const summaryDiv = document.getElementById("comparisonSummary");

                tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;
                summaryDiv.innerHTML = "";

                try {

                    const res = await fetch(
                        `https://localhost:7212/journal/monthPharmacySalesComparisonEndpoint?firstMoth=${firstMonth}&firstYear=${firstYear}&secondMonth=${secondMonth}&secondYear=${secondYear}`
                    );

                    // 🔥 ALWAYS READ RESPONSE BODY FIRST
                    const contentType = res.headers.get("content-type");

                    let responseData;

                    if (contentType && contentType.includes("application/json")) {
                        responseData = await res.json();
                    } else {
                        responseData = await res.text();
                    }

                    // 🔥 IF ERROR STATUS → THROW BACKEND MESSAGE
                    if (!res.ok) {
                        const errorMessage = typeof responseData === "string" ?
                            responseData :
                            responseData.message || "Unexpected error occurred";
                        throw new Error(errorMessage);
                    }

                    const d = responseData;

                    tbody.innerHTML = "";

                    // Transactions
                    const transDiff = percentDiff(d.partnerTotalTransactions, d.baseTotalTransactions);

                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Total Transactions</td>
                <td>${d.baseTotalTransactions}</td>
                <td>${d.partnerTotalTransactions}</td>
                <td class="${transDiff.class}">${transDiff.text}</td>
            </tr>
        `;

                    // Sales
                    const salesDiff = percentDiff(d.partnerTotalSales, d.baseTotalSales);

                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Total Sales</td>
                <td>${formatCurrency(d.baseTotalSales)}</td>
                <td>${formatCurrency(d.partnerTotalSales)}</td>
                <td class="${salesDiff.class}">${salesDiff.text}</td>
            </tr>
        `;

                    // Top Selling
                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Top Selling Item</td>
                <td>${d.baseTopSellingItem}</td>
                <td>${d.partnerTopSellingItem}</td>
                <td class="diff-neutral">—</td>
            </tr>
        `;

                    buildSummary(d);

                } catch (err) {

                    tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-danger fw-semibold">
                    ⚠ ${err.message}
                </td>
            </tr>
        `;

                    summaryDiv.innerHTML = `
            <div class="alert alert-danger mb-0">
                ${err.message}
            </div>
        `;
                }
            }
            // Load default
            loadComparison();
        </script>

    </div>
</body>

</html>