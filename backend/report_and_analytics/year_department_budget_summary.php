<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Yearly Department Budget Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .section-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: .5rem;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <!-- HEADER + YEAR SELECT -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-semibold mb-0">Department Yearly Budget Report</h4>
                <select id="yearSelector" class="form-select w-auto"></select>
            </div>

            <!-- SUMMARY -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Requested</div>
                        <h5 id="totalRequested">₱0</h5>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Allocated</div>
                        <h5 id="totalAllocated">₱0</h5>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Total Approved</div>
                        <h5 id="totalApproved">₱0</h5>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-3">
                        <div class="small-text">Year</div>
                        <h5 id="yearLabel">-</h5>
                    </div>
                </div>
            </div>

            <!-- CHART -->
            <div class="card p-4 mb-4" style="height:320px;">
                <canvas id="yearChart"></canvas>
            </div>

            <!-- ⭐ UPDATED PENDING SECTION (TABLE FORMAT) -->
            <div class="card p-3 mb-4">
                <div class="section-title">📌 Pending Budget Requests</div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-warning">
                            <tr>
                                <th>Month</th>
                                <th>Requested</th>
                                <th>Allocated</th>
                                <th>Approved</th>
                                <th>Status</th>
                                <th>Requested Date</th>
                            </tr>
                        </thead>
                        <tbody id="pendingTableBody">
                            <tr>
                                <td colspan="7" class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MONTH CARDS -->
            <div class="section-title mt-4">📅 Monthly Breakdown</div>
            <div class="row g-3" id="monthCards"></div>

        </div>
    </div>

    <script>
        const baseApi = "https://localhost:7212/journal/yearDepartmentBudgetSummaryReport?year=";
        const pendingApi = "https://localhost:7212/journal/yearPendingBudgetSummary?year=";

        let yearChart;

        // Color badge
        function getStatusColor(status) {
            switch (status.toLowerCase()) {
                case "approved":
                    return "bg-success";
                case "pending":
                    return "bg-warning text-dark";
                case "denied":
                    return "bg-danger";
                default:
                    return "bg-secondary";
            }
        }

        // Load year dropdown
        const yearSelect = document.getElementById("yearSelector");
        const currentYear = new Date().getFullYear();
        for (let y = currentYear; y >= currentYear - 5; y--) {
            yearSelect.innerHTML += `<option value="${y}">${y}</option>`;
        }

        yearSelect.addEventListener("change", () => loadReport(yearSelect.value));
        loadReport(yearSelect.value);

        function loadReport(year) {

            /* -----------------------------------
               LOAD MAIN YEARLY BUDGET SUMMARY
            ------------------------------------- */
            fetch(baseApi + year)
                .then(res => res.json())
                .then(data => {

                    document.getElementById("yearLabel").innerText = data.year;
                    document.getElementById("totalRequested").innerText = "₱" + data.total_requested.toLocaleString();
                    document.getElementById("totalAllocated").innerText = "₱" + data.total_allocated.toLocaleString();
                    document.getElementById("totalApproved").innerText = "₱" + data.total_approved.toLocaleString();

                    const labels = [];
                    const allocated = [];
                    const container = document.getElementById("monthCards");
                    container.innerHTML = "";

                    // MONTH CARDS
                    data.monthBudgetsReport.forEach(m => {

                        const monthName = new Date(m.month + "-01")
                            .toLocaleString("en-US", {
                                month: "long"
                            });

                        labels.push(monthName);
                        allocated.push(m.allocated_budget);

                        container.innerHTML += `
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card p-3 month-card">
                        <h6 class="fw-semibold">${monthName}</h6>

                        <div class="small-text">Requested</div>
                        <div class="fw-semibold">₱${m.requested_amount.toLocaleString()}</div>

                        <div class="small-text mt-1">Allocated</div>
                        <div>₱${m.allocated_budget.toLocaleString()}</div>

                        <div class="small-text mt-1">Approved</div>
                        <div>₱${m.approved_amount.toLocaleString()}</div>

                        <div class="small-text mt-1">Status</div>
                        <span class="badge ${getStatusColor(m.status)}">${m.status}</span>
                    </div>
                </div>`;
                    });

                    // UPDATE CHART
                    if (yearChart) yearChart.destroy();

                    yearChart = new Chart(document.getElementById("yearChart"), {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                label: "Allocated Budget",
                                data: allocated,
                                backgroundColor: "#0d6efd"
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
                                        callback: v => "₱" + v.toLocaleString()
                                    }
                                }
                            }
                        }
                    });
                });


            /* -----------------------------------
               ⭐ LOAD PENDING SECTION AS TABLE
            ------------------------------------- */
            fetch(pendingApi + year)
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById("pendingTableBody");
                    tbody.innerHTML = "";

                    if (data.length === 0) {
                        tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted">No pending budget requests.</td>
                    </tr>`;
                        return;
                    }

                    data.forEach(p => {
                        const monthName = new Date(p.month + "-01")
                            .toLocaleString("en-US", {
                                month: "long"
                            });

                        tbody.innerHTML += `
                    <tr>
                        <td><strong>${monthName}</strong></td>
                        <td>₱${p.requested_amount.toLocaleString()}</td>
                        <td>₱${p.allocated_budget.toLocaleString()}</td>
                        <td>₱${p.approved_amount.toLocaleString()}</td>
                        <td><span class="badge bg-warning text-dark">${p.status}</span></td>
                        <td>${new Date(p.request_date).toLocaleDateString()}</td>
                    </tr>
                `;
                    });
                });
        }
    </script>
</body>

</html>