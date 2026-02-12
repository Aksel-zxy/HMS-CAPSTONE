<?
include 'header.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Combined Insurance Forecast Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f9fafc;
            font-family: 'Segoe UI', sans-serif;
        }

        .card-premium {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .07)
        }

        .chart-container {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .05)
        }

        .stat-value {
            font-size: 26px;
            font-weight: 700
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d
        }

        .summary-text {
            font-size: 15px;
            color: #495057;
            line-height: 1.6
        }

        #insightsList li,
        #suggestionsList li {
            padding: 6px 0;
            color: #495057;
            font-size: 15px;
        }

        .severity-high {
            background: #dc3545;
            color: #fff;
        }

        .severity-medium {
            background: #ffc107;
            color: #212529;
        }

        .severity-low {
            background: #198754;
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?
        include 'sidebar.php'
        ?>
        <div class="container py-4">
            <div class="text-center mb-3">
                <h3 class="fw-bold">
                    <i class="bi bi-graph-up"></i> Insurance Forecast Dashboard
                </h3>
                <p class="text-muted" id="periodLabel">Loading period...</p>
            </div>

            <!-- SUMMARY -->
            <div class="card card-premium p-4 mb-4">
                <p id="summaryText" class="summary-text mb-0">
                    Loading insurance forecast summary...
                </p>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card card-premium p-3 text-center">
                        <div class="stat-value" id="totalApprovedAmount">—</div>
                        <div class="stat-label">Total Approved Amount</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-premium p-3 text-center">
                        <div class="stat-value" id="totalDeclinedAmount">—</div>
                        <div class="stat-label">Total Declined Amount</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-premium p-3 text-center">
                        <div class="stat-value" id="totalClaims">—</div>
                        <div class="stat-label">Total Claims</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-premium p-3 text-center">
                        <div class="stat-value" id="approvalRate">—</div>
                        <div class="stat-label">Approval Rate</div>
                    </div>
                </div>
            </div>

            <!-- EXPORT -->
            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-outline-primary me-2" onclick="exportCSV()">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </button>
                <button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </button>
            </div>

            <!-- CHARTS -->
            <div class="chart-container mb-4">
                <h6 class="fw-bold">Claim Amount Forecast per Provider</h6>
                <canvas id="amountChart" height="120"></canvas>
            </div>

            <div class="chart-container mb-4">
                <h6 class="fw-bold">Claim Status Forecast per Provider</h6>
                <canvas id="statusChart" height="120"></canvas>
            </div>

            <!-- INSIGHTS -->
            <div class="card card-premium p-4 mb-4">
                <h5 class="fw-bold">Key Insights</h5>
                <ul id="insightsList" class="mb-0"></ul>
            </div>

            <!-- SUGGESTIONS -->
            <div class="card card-premium p-4">
                <h5 class="fw-bold">
                    <i class="bi bi-lightbulb me-2 text-warning"></i>
                    Operational Suggestions
                </h5>
                <ul id="suggestionsList" class="mb-0"></ul>
            </div>
        </div>

        <script>
            const providerMap = {
                1: "PhilHealth",
                2: "Maxicare",
                3: "Medicard",
                4: "Intellicare",
                5: "Pacific Cross",
                6: "Cocolife",
                7: "AXA",
                8: "Sun Life",
                9: "Manulife",
                10: "PGA"
            };

            let amountChart, statusChart;
            let mergedGlobal = [];

            async function loadAll() {
                const now = new Date();
                const month = now.getMonth() + 1;
                const year = now.getFullYear();

                const monthName = new Date(year, month - 1).toLocaleString('default', {
                    month: 'long'
                });
                document.getElementById('periodLabel').innerText = `${monthName} ${year}`;

                const amountUrl = `https://localhost:7212/insurance/getMonthProviderAmountForecast?month=${month}&year=${year}`;
                const statusUrl = `https://localhost:7212/insurance/getMonthProviderStatusForecast?month=${month}&year=${year}`;

                try {
                    const [amountRes, statusRes] = await Promise.all([
                        fetch(amountUrl),
                        fetch(statusUrl)
                    ]);

                    const amountData = await amountRes.json();
                    const statusData = await statusRes.json();

                    const merged = amountData.map(a => {
                        const s = statusData.find(x => x.insurance_provider_id === a.insurance_provider_id) || {
                            total_claims: 0,
                            total_claim_approved: 0,
                            total_claim_denied: 0
                        };

                        return {
                            provider_id: a.insurance_provider_id,
                            name: providerMap[a.insurance_provider_id] || `Provider ${a.insurance_provider_id}`,
                            approved_amount: a.total_claim_approved_amount,
                            declined_amount: a.total_claim_declined_amount,
                            total_claims: s.total_claims,
                            approved_claims: s.total_claim_approved,
                            denied_claims: s.total_claim_denied
                        };
                    });

                    mergedGlobal = merged;

                    const totalApprovedAmount = merged.reduce((s, x) => s + x.approved_amount, 0);
                    const totalDeclinedAmount = merged.reduce((s, x) => s + x.declined_amount, 0);
                    const totalClaims = merged.reduce((s, x) => s + x.total_claims, 0);
                    const totalApprovedClaims = merged.reduce((s, x) => s + x.approved_claims, 0);

                    const avgApprovedPerClaim = totalApprovedClaims ? totalApprovedAmount / totalApprovedClaims : 0;

                    document.getElementById('totalApprovedAmount').innerText =
                        totalApprovedAmount.toLocaleString(undefined, {
                            style: 'currency',
                            currency: 'PHP'
                        });

                    document.getElementById('totalDeclinedAmount').innerText =
                        totalDeclinedAmount.toLocaleString(undefined, {
                            style: 'currency',
                            currency: 'PHP'
                        });

                    document.getElementById('totalClaims').innerText = totalClaims;

                    document.getElementById('approvalRate').innerText =
                        totalClaims ? ((totalApprovedClaims / totalClaims) * 100).toFixed(2) + '%' : '0%';

                    document.getElementById('summaryText').innerHTML = `
                    For <strong>${monthName} ${year}</strong>, insurers are expected to process
                    <strong>${totalClaims}</strong> claims, with
                    <strong>${totalApprovedAmount.toLocaleString(undefined, { style: 'currency', currency: 'PHP' })}</strong>
                    in approved payouts and
                    <strong>${totalDeclinedAmount.toLocaleString(undefined, { style: 'currency', currency: 'PHP' })}</strong>
                    in declined amounts.
                `;

                    renderAmountChart(merged);
                    renderStatusChart(merged);
                    buildInsights(merged);
                    buildSuggestions(merged, totalApprovedAmount, totalDeclinedAmount, totalClaims, avgApprovedPerClaim);

                } catch (err) {
                    console.error(err);
                    alert('Failed to fetch insurance forecast data. Make sure the API endpoints are running.');
                }
            }

            function renderAmountChart(data) {
                const ctx = document.getElementById('amountChart');
                if (amountChart) amountChart.destroy();

                const bgColors = data.map(x =>
                    x.declined_amount > x.approved_amount * 0.5 ? '#dc3545aa' : '#0d6efdaa'
                );

                amountChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(x => x.name),
                        datasets: [{
                            label: 'Approved Amount',
                            data: data.map(x => x.approved_amount),
                            backgroundColor: bgColors
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            function renderStatusChart(data) {
                const ctx = document.getElementById('statusChart');
                if (statusChart) statusChart.destroy();

                statusChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(x => x.name),
                        datasets: [{
                            label: 'Approved Claims',
                            data: data.map(x => x.approved_claims),
                            backgroundColor: '#198754aa'
                        }, {
                            label: 'Denied Claims',
                            data: data.map(x => x.denied_claims),
                            backgroundColor: '#dc3545aa'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            function buildInsights(data) {
                const topAmount = data.reduce((a, b) => a.approved_amount > b.approved_amount ? a : b);
                const topClaims = data.reduce((a, b) => a.total_claims > b.total_claims ? a : b);

                const insights = [];
                insights.push(`Highest approved payout forecast: ${topAmount.name}`);
                insights.push(`Highest claim volume: ${topClaims.name}`);
                insights.push('Overall claim activity remains stable.');

                document.getElementById('insightsList').innerHTML =
                    insights.map(i => `<li>• ${i}</li>`).join('');
            }

            function buildSuggestions(data, totalApprovedAmount, totalDeclinedAmount, totalClaims, avgApprovedPerClaim) {
                const suggestions = [];

                const approvalRate = totalClaims ?
                    (data.reduce((s, x) => s + x.approved_claims, 0) / totalClaims) * 100 :
                    0;

                suggestions.push({
                    text: approvalRate < 70 ?
                        'Approval rate is below optimal. Review documentation quality.' : 'Approval rate is healthy. Maintain current standards.',
                    severity: approvalRate < 70 ? 'high' : 'low'
                });

                suggestions.push({
                    text: totalDeclinedAmount > totalApprovedAmount * 0.4 ?
                        'High declined payout volume detected. Investigate rejection reasons.' : 'Declined payout volume is within acceptable range.',
                    severity: totalDeclinedAmount > totalApprovedAmount * 0.4 ? 'high' : 'low'
                });

                suggestions.push({
                    text: avgApprovedPerClaim > 3000 ?
                        'High average approved claim amount. Ensure cost controls.' : 'Average claim size is stable.',
                    severity: avgApprovedPerClaim > 3000 ? 'medium' : 'low'
                });

                const topProvider = data.reduce((a, b) =>
                    a.approved_amount > b.approved_amount ? a : b
                );

                suggestions.push({
                    text: `Strengthen relationship with ${topProvider.name} (highest payout volume).`,
                    severity: 'medium'
                });

                document.getElementById('suggestionsList').innerHTML =
                    suggestions.map(s => `
                    <li>
                        <span class="badge me-2 severity-${s.severity}">
                            ${s.severity.toUpperCase()}
                        </span>
                        ${s.text}
                    </li>
                `).join('');
            }

            function exportCSV() {
                let csv = "Provider,Approved Amount,Declined Amount,Total Claims\n";
                mergedGlobal.forEach(x => {
                    csv += `${x.name},${x.approved_amount},${x.declined_amount},${x.total_claims}\n`;
                });

                const blob = new Blob([csv], {
                    type: 'text/csv'
                });
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = "insurance_forecast.csv";
                link.click();
            }

            window.onload = loadAll;
        </script>
    </div>
</body>

</html>