<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Revenue Comparison</title>
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

            <h3 class="fw-bold mb-4 text-center">💰 Monthly Revenue Comparison</h3>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Base Month</label>
                    <input type="month" id="baseMonthInput" class="form-control" value="2025-02">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Partner Month</label>
                    <input type="month" id="partnerMonthInput" class="form-control" value="2025-01">
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

                const serviceDiff = percentDiff(data.baseServiceRevenue, data.partnerServiceRevenue);
                const pharmacyDiff = percentDiff(data.basePharmacyRevenue, data.partnerPharmacyRevenue);
                const totalDiff = percentDiff(data.baseTotalRevenue, data.partnerTotalRevenue);

                let summaryHtml = `
        <strong>${data.baseMonth}/${data.baseYear} vs ${data.partnerMonth}/${data.partnerYear}</strong><br><br>
    `;

                summaryHtml += `
        • Service Revenue changed by 
        <span class="${serviceDiff.class}">
            ${serviceDiff.value.toFixed(2)}%
        </span><br>
    `;

                summaryHtml += `
        • Pharmacy Revenue changed by 
        <span class="${pharmacyDiff.class}">
            ${pharmacyDiff.value.toFixed(2)}%
        </span><br>
    `;

                summaryHtml += `
        • Total Revenue changed by 
        <span class="${totalDiff.class}">
            ${totalDiff.value.toFixed(2)}%
        </span>
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
                        `https://bsis-03.keikaizen.xyz/journal/monthRevenueComparisonEndpoint?month=${baseMonth}&year=${baseYear}&partnerMonth=${partnerMonth}&partnerYear=${partnerYear}`
                    );

                    const contentType = res.headers.get("content-type");
                    let responseData;

                    if (contentType && contentType.includes("application/json")) {
                        responseData = await res.json();
                    } else {
                        responseData = await res.text();
                    }

                    if (!res.ok) {
                        const errorMessage = typeof responseData === "string" ?
                            responseData :
                            responseData.message || "Unexpected error occurred";
                        throw new Error(errorMessage);
                    }

                    const d = responseData;

                    tbody.innerHTML = "";

                    const serviceDiff = percentDiff(d.baseServiceRevenue, d.partnerServiceRevenue);
                    const pharmacyDiff = percentDiff(d.basePharmacyRevenue, d.partnerPharmacyRevenue);
                    const totalDiff = percentDiff(d.baseTotalRevenue, d.partnerTotalRevenue);

                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Service Revenue</td>
                <td>${formatCurrency(d.baseServiceRevenue)}</td>
                <td>${formatCurrency(d.partnerServiceRevenue)}</td>
                <td class="${serviceDiff.class}">${serviceDiff.text}</td>
            </tr>
        `;

                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Pharmacy Revenue</td>
                <td>${formatCurrency(d.basePharmacyRevenue)}</td>
                <td>${formatCurrency(d.partnerPharmacyRevenue)}</td>
                <td class="${pharmacyDiff.class}">${pharmacyDiff.text}</td>
            </tr>
        `;

                    tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">Total Revenue</td>
                <td>${formatCurrency(d.baseTotalRevenue)}</td>
                <td>${formatCurrency(d.partnerTotalRevenue)}</td>
                <td class="${totalDiff.class}">${totalDiff.text}</td>
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

            loadComparison();
        </script>

    </div>
</body>

</html>