<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Department Budget Summary</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .breakdown-box {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .breakdown-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .breakdown-item:last-child {
            border-bottom: none;
        }

        .percent {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0d6efd;
        }

        .insight-box {
            border-left: 4px solid #0d6efd;
            padding: 15px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <?php include 'sidebar.php'; ?>

        <div class="container py-5">

            <h2 class="text-center mb-4" id="reportTitle">Budget Summary Report</h2>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card p-3 text-center summary-card">
                        <h6>Requested Amount</h6>
                        <h3 id="requestedAmount">-</h3>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center summary-card">
                        <h6>Approved Amount</h6>
                        <h3 id="approvedAmount">-</h3>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center summary-card">
                        <h6>Allocated Budget</h6>
                        <h3 id="allocatedBudget">-</h3>
                    </div>
                </div>
            </div>

            <!-- Breakdown Section -->
            <div class="breakdown-box mb-4">
                <h5 class="mb-3">Budget Breakdown & Percentages</h5>

                <div class="breakdown-item">
                    <strong>Approved vs Requested:</strong>
                    <span class="float-end percent" id="pctApproved"></span>
                </div>

                <div class="breakdown-item">
                    <strong>Allocated vs Requested:</strong>
                    <span class="float-end percent" id="pctAllocatedRequested"></span>
                </div>

                <div class="breakdown-item">
                    <strong>Allocated vs Approved:</strong>
                    <span class="float-end percent" id="pctAllocatedApproved"></span>
                </div>
            </div>

            <!-- Insights -->
            <h4 class="mt-4 mb-3">Key Insights</h4>
            <div id="insightsContainer" class="insight-box">
                Loading insights...
            </div>

        </div>
    </div>

    <script>
        // Get month and year from URL
        const params = new URLSearchParams(window.location.search);
        const month = params.get("month");
        const year = params.get("year");

        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        // Convert "2025-09" → month number
        const cleanMonth = month.includes("-") ? month.split("-")[1] : month;

        document.getElementById("reportTitle").innerText =
            `${monthNames[cleanMonth - 1]} ${year} Budget Summary`;

        async function loadReport() {
            const url = `https://bsis-03.keikaizen.xyz/journal/monthDepartmentBudgetSummaryReport?month=${cleanMonth}&year=${year}`;

            const res = await fetch(url);
            const data = await res.json();

            const requested = data.requested_amount;
            const approved = data.approved_amount;
            const allocated = data.allocated_budget;

            requestedAmount.innerText = "₱" + requested.toLocaleString();
            approvedAmount.innerText = "₱" + approved.toLocaleString();
            allocatedBudget.innerText = "₱" + allocated.toLocaleString();

            const pctApproved = (approved / requested) * 100;
            const pctAllocReq = (allocated / requested) * 100;
            const pctAllocApp = approved === 0 ? 0 : (allocated / approved) * 100;

            document.getElementById("pctApproved").innerText = pctApproved.toFixed(1) + "%";
            document.getElementById("pctAllocatedRequested").innerText = pctAllocReq.toFixed(1) + "%";
            document.getElementById("pctAllocatedApproved").innerText = pctAllocApp.toFixed(1) + "%";

            generateInsights(pctApproved, pctAllocReq, pctAllocApp);
        }

        function generateInsights(p1, p2, p3) {
            let insights = [];

            insights.push(`Approved budget is <b>${p1.toFixed(1)}%</b> of the requested amount.`);
            insights.push(`Allocated funds represent <b>${p2.toFixed(1)}%</b> of the total request.`);
            insights.push(`Allocated budget covers <b>${p3.toFixed(1)}%</b> of the approved amount.`);

            if (p1 < 80) insights.push("Approval rate is noticeably below full request.");
            if (p2 < 70) insights.push("Allocated funds fall significantly short of what was requested.");

            document.getElementById("insightsContainer").innerHTML =
                insights.map(i => `<p>• ${i}</p>`).join("");
        }

        loadReport();
    </script>

</body>

</html>