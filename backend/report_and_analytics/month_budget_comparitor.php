<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Budget Comparison</title>
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

            <h3 class="fw-bold mb-4 text-center">📊 Monthly Budget Comparison</h3>

            <!-- ALERT CONTAINER -->
            <div id="alertBox"></div>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <input type="number" id="baseMonth" class="form-control" value="9">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <input type="number" id="baseYear" class="form-control" value="2025">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Partner Month</label>
                    <input type="number" id="partnerMonth" class="form-control" value="10">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Partner Year</label>
                    <input type="number" id="partnerYear" class="form-control" value="2025">
                </div>

                <div class="col-md-3 d-flex align-items-end mt-3">
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
                                <th id="baseLabel">Base</th>
                                <th id="partnerLabel">Partner</th>
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
        function percentDiff(base, partner) {
            if (base === 0) return {
                text: "N/A",
                class: "diff-neutral",
                value: 0
            };

            const diff = ((partner - base) / base) * 100;

            return {
                text: diff > 0 ? `▲ ${diff.toFixed(2)}%` : diff < 0 ? `▼ ${Math.abs(diff).toFixed(2)}%` : "0%",
                class: diff > 0 ? "diff-up" : diff < 0 ? "diff-down" : "diff-neutral",
                value: diff
            };
        }

        function showError(message) {
            document.getElementById("alertBox").innerHTML = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
        }

        function buildSummary(rows, baseLabel, partnerLabel) {
            let summary = `<strong>${baseLabel} vs ${partnerLabel}</strong><br>`;
            let biggest = {
                name: "",
                value: 0
            };

            rows.forEach(r => {
                const diff = percentDiff(r.base, r.partner);

                if (Math.abs(diff.value) > biggest.value) {
                    biggest = {
                        name: r.metric,
                        value: Math.abs(diff.value)
                    };
                }

                if (diff.value > 0)
                    summary += `• <strong>${r.metric}</strong> increased by <span class="text-success">${diff.value.toFixed(2)}%</span><br>`;
                else if (diff.value < 0)
                    summary += `• <strong>${r.metric}</strong> decreased by <span class="text-danger">${Math.abs(diff.value).toFixed(2)}%</span><br>`;
                else
                    summary += `• <strong>${r.metric}</strong> remained unchanged<br>`;
            });

            if (biggest.name)
                summary += `<br>The most significant change was in <strong>${biggest.name}</strong>.`;

            document.getElementById("comparisonSummary").innerHTML = summary;
        }

        async function loadComparison() {

            const m = document.getElementById("baseMonth").value;
            const y = document.getElementById("baseYear").value;
            const pm = document.getElementById("partnerMonth").value;
            const py = document.getElementById("partnerYear").value;

            const baseLabel = `${m}-${y}`;
            const partnerLabel = `${pm}-${py}`;

            document.getElementById("baseLabel").innerText = baseLabel;
            document.getElementById("partnerLabel").innerText = partnerLabel;

            const tbody = document.getElementById("comparisonBody");
            tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

            try {
                const res = await fetch(
                    `https://localhost:7212/journal/monthBudgetComparitor?month=${m}&year=${y}&partnerMonth=${pm}&partnerYear=${py}`
                );

                // FIRST: Check HTTP response before parsing JSON
                if (!res.ok) {
                    const text = await res.text();
                    showError(text || "Server error.");
                    tbody.innerHTML = `<tr><td colspan="4" class="text-danger">No data available</td></tr>`;
                    return;
                }

                // Parse JSON after confirming good status
                const data = await res.json();

                // SECOND: Check for API-provided errors
                if (data.error || data.message) {
                    showError(data.error || data.message);
                    tbody.innerHTML = `<tr><td colspan="4" class="text-danger">No data available</td></tr>`;
                    document.getElementById("comparisonSummary").innerHTML = "Summary unavailable.";
                    return;
                }

                // If no error → process table rows
                const rows = [{
                        metric: "Requested Amount",
                        base: data.requested_amount,
                        partner: data.partnerrequested_amount
                    },
                    {
                        metric: "Approved Amount",
                        base: data.approved_amount,
                        partner: data.partnerapproved_amount
                    },
                    {
                        metric: "Allocated Budget",
                        base: data.allocated_budget,
                        partner: data.partnerallocated_budget
                    }
                ];

                tbody.innerHTML = "";

                rows.forEach(r => {
                    const diff = percentDiff(r.base, r.partner);

                    tbody.innerHTML += `
                <tr>
                    <td class="fw-semibold">${r.metric}</td>
                    <td>${r.base.toLocaleString()}</td>
                    <td>${r.partner.toLocaleString()}</td>
                    <td class="${diff.class}">${diff.text}</td>
                </tr>
            `;
                });

                buildSummary(rows, baseLabel, partnerLabel);

            } catch (err) {
                showError("Network or server error.");
                tbody.innerHTML = `<tr><td colspan="4" class="text-danger">Failed to load data</td></tr>`;
                document.getElementById("comparisonSummary").innerHTML = "Summary unavailable.";
            }
        }

        loadComparison();
    </script>

</body>

</html>