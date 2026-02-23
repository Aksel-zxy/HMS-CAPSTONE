<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Insurance Claims Comparison</title>
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

            <h3 class="fw-bold mb-4 text-center">📊 Monthly Insurance Claims Comparison</h3>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Base Month</label>
                    <input type="month" id="baseMonthInput" class="form-control" value="2025-07">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Partner Month</label>
                    <input type="month" id="partnerMonthInput" class="form-control" value="2025-08">
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
                    text: diff > 0 ?
                        `▲ ${diff.toFixed(2)}%` : diff < 0 ?
                        `▼ ${Math.abs(diff).toFixed(2)}%` : "0%",
                    class: diff > 0 ?
                        "diff-up" : diff < 0 ?
                        "diff-down" : "diff-neutral",
                    value: diff
                };
            }

            function buildSummary(d) {
                const totalDiff = percentDiff(d.base_total_claims, d.partner_total_claims);
                const appDiff = percentDiff(d.base_total_approved_claims, d.partner_total_approved_claims);
                const denDiff = percentDiff(d.base_total_denied_claims, d.partner_total_denied_claims);

                let html = `
                <strong>${d.basemonth}/${d.baseyear} vs ${d.partnermonth}/${d.partneryear}</strong><br><br>
                • Total claims changed by <span class="${totalDiff.class}">${totalDiff.value.toFixed(2)}%</span><br>
                • Approved claims changed by <span class="${appDiff.class}">${appDiff.value.toFixed(2)}%</span><br>
                • Denied claims changed by <span class="${denDiff.class}">${denDiff.value.toFixed(2)}%</span>
            `;

                document.getElementById("comparisonSummary").innerHTML = html;
            }

            async function loadComparison() {
                const baseVal = document.getElementById("baseMonthInput").value;
                const partnerVal = document.getElementById("partnerMonthInput").value;

                const [baseYear, baseMonth] = baseVal.split("-").map(Number);
                const [partnerYear, partnerMonth] = partnerVal.split("-").map(Number);

                document.getElementById("baseMonthLabel").innerText = `${baseMonth}/${baseYear}`;
                document.getElementById("partnerMonthLabel").innerText = `${partnerMonth}/${partnerYear}`;

                const tbody = document.getElementById("comparisonBody");
                tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

                try {
                    const res = await fetch(
                        `https://localhost:7212/insurance/monthClaimComparisonEndpoint?month=${baseMonth}&year=${baseYear}&partnerMonth=${partnerMonth}&partnerYear=${partnerYear}`
                    );

                    if (!res.ok) throw new Error("Fetch error");

                    const d = await res.json();
                    tbody.innerHTML = "";

                    // Total Claims
                    const totalDiff = percentDiff(d.base_total_claims, d.partner_total_claims);
                    tbody.innerHTML += `
                    <tr>
                        <td class="fw-semibold">Total Claims</td>
                        <td>${d.base_total_claims}</td>
                        <td>${d.partner_total_claims}</td>
                        <td class="${totalDiff.class}">${totalDiff.text}</td>
                    </tr>
                `;

                    // Approved Claims
                    const appDiff = percentDiff(d.base_total_approved_claims, d.partner_total_approved_claims);
                    tbody.innerHTML += `
                    <tr>
                        <td class="fw-semibold">Approved Claims</td>
                        <td>${d.base_total_approved_claims}</td>
                        <td>${d.partner_total_approved_claims}</td>
                        <td class="${appDiff.class}">${appDiff.text}</td>
                    </tr>
                `;

                    // Denied Claims
                    const denDiff = percentDiff(d.base_total_denied_claims, d.partner_total_denied_claims);
                    tbody.innerHTML += `
                    <tr>
                        <td class="fw-semibold">Denied Claims</td>
                        <td>${d.base_total_denied_claims}</td>
                        <td>${d.partner_total_denied_claims}</td>
                        <td class="${denDiff.class}">${denDiff.text}</td>
                    </tr>
                `;

                    buildSummary(d);

                } catch (err) {
                    tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-danger fw-semibold">
                            ⚠ Unable to fetch insurance comparison data.
                        </td>
                    </tr>
                `;
                    document.getElementById("comparisonSummary").innerHTML = `
                    <div class="alert alert-danger mb-0">Unable to generate summary.</div>
                `;
                }
            }

            loadComparison();
        </script>

    </div>
</body>

</html>