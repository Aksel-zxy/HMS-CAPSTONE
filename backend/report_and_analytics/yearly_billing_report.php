<?php
include 'header.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Yearly Billing Comparison</title>
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
        <?php
        include 'sidebar.php'
        ?>
        <div class="container my-5">

            <h3 class="fw-bold mb-4 text-center">📊 Yearly Billing Comparison</h3>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Base Year</label>
                    <input type="number" id="baseYear" class="form-control" value="2025">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Compared Year</label>
                    <input type="number" id="comparedYear" class="form-control" value="2024">
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
                                <th id="baseYearLabel">Base Year</th>
                                <th id="comparedYearLabel">Compared Year</th>
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
                    text: diff > 0 ?
                        `▲ ${diff.toFixed(2)}%` : diff < 0 ?
                        `▼ ${Math.abs(diff).toFixed(2)}%` : "0%",
                    class: diff > 0 ? "diff-up" : diff < 0 ? "diff-down" : "diff-neutral",
                    value: diff
                };
            }

            function buildSummary(rows, baseYear, comparedYear) {
                let summaryHtml = `<strong>${baseYear} vs ${comparedYear}</strong><br>`;
                let biggestChange = {
                    name: "",
                    value: 0
                };

                rows.forEach(r => {
                    const diff = percentDiff(r[1], r[2]);

                    if (Math.abs(diff.value) > biggestChange.value) {
                        biggestChange = {
                            name: r[0],
                            value: Math.abs(diff.value)
                        };
                    }

                    if (diff.value > 0) {
                        summaryHtml += `• <strong>${r[0]}</strong> increased by <span class="text-success">${diff.value.toFixed(2)}%</span><br>`;
                    } else if (diff.value < 0) {
                        summaryHtml += `• <strong>${r[0]}</strong> decreased by <span class="text-danger">${Math.abs(diff.value).toFixed(2)}%</span><br>`;
                    } else {
                        summaryHtml += `• <strong>${r[0]}</strong> remained unchanged<br>`;
                    }
                });

                if (biggestChange.name) {
                    summaryHtml += `<br>The most significant change was <strong>${biggestChange.name}</strong>.`;
                }

                document.getElementById("comparisonSummary").innerHTML = summaryHtml;
            }

            async function loadComparison() {
                const year = document.getElementById("baseYear").value;
                const comparedYear = document.getElementById("comparedYear").value;

                document.getElementById("baseYearLabel").innerText = year;
                document.getElementById("comparedYearLabel").innerText = comparedYear;

                const tbody = document.getElementById("comparisonBody");
                tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

                try {
                    const res = await fetch(`https://localhost:7212/journal/getYearBillSummaryReport?year=${year}&comparedYear=${comparedYear}`);
                    if (!res.ok) throw new Error("Failed to fetch data");

                    const d = await res.json();

                    const rows = [
                        ["Total Billed", d.total_billed, d.prev_total_billed],
                        ["Total Paid", d.total_paid, d.prev_total_paid],
                        ["Pending Transactions", d.total_pending_transaction, d.prev_total_pending_transaction],
                        ["Out-of-Pocket Collected", d.total_oop_collected, d.prev_total_oop_collected],
                        ["Insurance Covered", d.total_insurance_covered, d.prev_total_insurance_covered],
                        ["Pending Amount", d.total_pending_amount, d.prev_total_pending_amount]
                    ];

                    tbody.innerHTML = "";

                    rows.forEach(r => {
                        const diff = percentDiff(r[1], r[2]);
                        tbody.innerHTML += `
                    <tr>
                        <td class="fw-semibold">${r[0]}</td>
                        <td>${r[1].toLocaleString()}</td>
                        <td>${r[2].toLocaleString()}</td>
                        <td class="${diff.class}">${diff.text}</td>
                    </tr>
                `;
                    });

                    buildSummary(rows, year, comparedYear);

                } catch (err) {
                    console.error(err);
                    tbody.innerHTML = `<tr><td colspan="4" class="text-danger">Failed to load comparison</td></tr>`;
                    document.getElementById("comparisonSummary").innerHTML = "Unable to generate summary.";
                }
            }

            // Load default comparison
            loadComparison();
        </script>
    </div>
</body>

</html>