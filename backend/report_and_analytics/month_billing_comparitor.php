<?php include 'header.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Billing Comparison</title>
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

        <?php include 'sidebar.php' ?>

        <div class="container my-5">

            <h3 class="fw-bold mb-4 text-center">📊 Monthly Billing Comparison</h3>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">

                <!-- First Month -->
                <div class="col-md-3">
                    <label class="form-label">Base Month</label>
                    <select id="firstMonth" class="form-select">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>

                <!-- First Year -->
                <div class="col-md-3">
                    <label class="form-label">Base Year</label>
                    <input type="number" id="firstYear" class="form-control" value="2025">
                </div>

                <!-- Second Month -->
                <div class="col-md-3">
                    <label class="form-label">Partner Month</label>
                    <select id="secondMonth" class="form-select">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>

                <!-- Second Year -->
                <div class="col-md-3">
                    <label class="form-label">Partner Year</label>
                    <input type="number" id="secondYear" class="form-control" value="2025">
                </div>

                <!-- Compare Button -->
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
                <div id="comparisonSummary" class="text-muted">No summary available.</div>
            </div>

        </div>

        <script>
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

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

            function buildSummary(rows, firstLabel, secondLabel) {
                let summary = `<strong>${firstLabel} vs ${secondLabel}</strong><br>`;
                let biggest = {
                    name: "",
                    value: 0
                };

                rows.forEach(r => {
                    const diff = percentDiff(r[1], r[2]);
                    if (Math.abs(diff.value) > Math.abs(biggest.value)) {
                        biggest = {
                            name: r[0],
                            value: diff.value
                        };
                    }

                    if (diff.value > 0)
                        summary += `• <strong>${r[0]}</strong> increased by <span class="text-success">${diff.value.toFixed(2)}%</span><br>`;
                    else if (diff.value < 0)
                        summary += `• <strong>${r[0]}</strong> decreased by <span class="text-danger">${Math.abs(diff.value).toFixed(2)}%</span><br>`;
                    else
                        summary += `• <strong>${r[0]}</strong> remained unchanged<br>`;
                });

                if (biggest.name)
                    summary += `<br>Most significant change: <strong>${biggest.name}</strong>.`;

                document.getElementById("comparisonSummary").innerHTML = summary;
            }

            async function loadComparison() {
                const firstMonth = document.getElementById("firstMonth").value;
                const firstYear = document.getElementById("firstYear").value;
                const secondMonth = document.getElementById("secondMonth").value;
                const secondYear = document.getElementById("secondYear").value;

                const baseLabel = `${monthNames[firstMonth - 1]} ${firstYear}`;
                const partnerLabel = `${monthNames[secondMonth - 1]} ${secondYear}`;

                document.getElementById("baseMonthLabel").innerText = baseLabel;
                document.getElementById("partnerMonthLabel").innerText = partnerLabel;

                const tbody = document.getElementById("comparisonBody");
                tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

                try {
                    const url =
                        `https://localhost:7212/journal/monthBillingReportComparisonEndpoint?` +
                        `firstMoth=${firstMonth}&firstYear=${firstYear}` +
                        `&secondMonth=${secondMonth}&secondYear=${secondYear}`;

                    const res = await fetch(url);
                    const d = await res.json();

                    const rows = [
                        ["Total Billed", d.total_billed, d.partnertotal_billed],
                        ["Total Paid", d.total_paid, d.partnertotal_paid],
                        ["Pending Transactions", d.total_pending_transaction, d.partnertotal_pending_transaction],
                        ["OOP Collected", d.total_oop_collected, d.partnertotal_oop_collected],
                        ["Insurance Covered", d.total_insurance_covered, d.partnertotal_insurance_covered],
                        ["Pending Amount", d.total_pending_amount, d.partnertotal_pending_amount]
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

                    buildSummary(rows, baseLabel, partnerLabel);

                } catch (err) {
                    tbody.innerHTML =
                        `<tr><td colspan="4" class="text-danger">Error loading data</td></tr>`;
                    document.getElementById("comparisonSummary").innerHTML =
                        "Unable to generate summary.";
                }
            }

            loadComparison();
        </script>

    </div>
</body>

</html>