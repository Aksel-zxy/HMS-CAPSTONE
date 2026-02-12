<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Yearly Budget Comparison</title>
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

            <h3 class="fw-bold mb-4 text-center">📊 Yearly Budget Comparison</h3>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Base Year</label>
                    <input type="number" id="baseYear" class="form-control" value="2025">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Compared Year</label>
                    <input type="number" id="partnerYear" class="form-control" value="2024">
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
                                <th id="partnerYearLabel">Compared Year</th>
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

        function buildSummary(rows, baseYear, partnerYear) {
            let summary = `<strong>${baseYear} vs ${partnerYear}</strong><br>`;
            let biggest = {
                name: "",
                value: 0
            };

            rows.forEach(r => {
                const diff = percentDiff(r[1], r[2]);

                if (Math.abs(diff.value) > biggest.value) {
                    biggest = {
                        name: r[0],
                        value: Math.abs(diff.value)
                    };
                }

                if (diff.value > 0) {
                    summary += `• <strong>${r[0]}</strong> increased by <span class="text-success">${diff.value.toFixed(2)}%</span><br>`;
                } else if (diff.value < 0) {
                    summary += `• <strong>${r[0]}</strong> decreased by <span class="text-danger">${Math.abs(diff.value).toFixed(2)}%</span><br>`;
                } else {
                    summary += `• <strong>${r[0]}</strong> remained unchanged<br>`;
                }
            });

            if (biggest.name) {
                summary += `<br>The most significant change was <strong>${biggest.name}</strong>.`;
            }

            document.getElementById("comparisonSummary").innerHTML = summary;
        }

        async function loadComparison() {
            const year = document.getElementById("baseYear").value;
            const partnerYear = document.getElementById("partnerYear").value;

            document.getElementById("baseYearLabel").innerText = year;
            document.getElementById("partnerYearLabel").innerText = partnerYear;

            const tbody = document.getElementById("comparisonBody");
            tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

            try {
                const res = await fetch(
                    `https://localhost:7212/billing/compareYearBudgets?year=${year}&partnerYear=${partnerYear}`
                );

                if (!res.ok) throw new Error("Failed to fetch");

                const d = await res.json();

                const rows = [
                    ["Total Allocated", d.baseTotalAllocated, d.comparedBaseTotalAllocated],
                    ["Total Approved", d.baseTotalApproved, d.comparedBaseTotalApproved],
                    ["Total Requested", d.baseTotalRequested, d.comparedBaseTotalRequested]
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

                buildSummary(rows, year, partnerYear);

            } catch (err) {
                console.error(err);
                tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-danger">Failed to load comparison</td>
                </tr>
            `;
                document.getElementById("comparisonSummary").innerHTML =
                    "Unable to generate summary.";
            }
        }

        // Load default data
        loadComparison();
    </script>

</body>

</html>