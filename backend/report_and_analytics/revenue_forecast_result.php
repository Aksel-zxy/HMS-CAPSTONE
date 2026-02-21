<?
include 'header.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Hospital Revenue Forecast Dashboard</title>

    <!-- Premium Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        body {
            background: #f9fafc;
            font-family: 'Segoe UI', sans-serif;
        }

        .card-premium {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .07);
            transition: transform 0.2s ease;
        }

        .card-premium:hover {
            transform: translateY(-4px);
        }

        .stat-icon {
            font-size: 30px;
            opacity: .8;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
        }

        .chart-container {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .05);
        }

        .summary-text {
            font-size: 16px;
            color: #495057;
            line-height: 1.6;
        }

        #suggestionsList li,
        #insightsList li {
            padding: 6px 0;
            color: #495057;
            font-size: 15px;
        }

        .severity-high {
            color: #d63384;
            font-weight: 600;
        }

        .severity-medium {
            color: #fd7e14;
            font-weight: 600;
        }

        .severity-low {
            color: #198754;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?
        include 'sidebar.php'
        ?>
        <div class="container py-5" id="dashboardContainer">

            <!-- HEADER -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold">
                        <i class="bi bi-hospital me-2"></i>
                        Monthly Hospital Revenue Forecast
                    </h2>
                    <p class="text-muted mb-0" id="periodLabel">Loading period...</p>
                </div>
                <button class="btn btn-primary" id="downloadBtn">
                    <i class="bi bi-download me-1"></i> Download PDF
                </button>
            </div>

            <!-- SUMMARY TEXT -->
            <div class="card card-premium p-4 mb-4">
                <p id="summaryText" class="summary-text mb-0">Loading hospital revenue forecast...</p>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card card-premium p-3 text-center">
                        <i class="bi bi-currency-dollar stat-icon text-success mb-2"></i>
                        <div class="stat-value" id="totalRevenue">—</div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-premium p-3 text-center">
                        <i class="bi bi-receipt stat-icon text-primary mb-2"></i>
                        <div class="stat-value" id="totalTransactions">—</div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-premium p-3 text-center">
                        <i class="bi bi-calculator stat-icon text-warning mb-2"></i>
                        <div class="stat-value" id="averageBill">—</div>
                        <div class="stat-label">Average Bill Amount</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-premium p-3 text-center">
                        <i class="bi bi-graph-up stat-icon text-info mb-2"></i>
                        <div class="stat-value" id="revPerTxn">—</div>
                        <div class="stat-label">Revenue / Transaction</div>
                    </div>
                </div>
            </div>

            <!-- TREND CHART -->
            <div class="chart-container mb-4">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-graph-up-arrow me-1"></i>
                    Revenue Trend (Historical + Forecast)
                </h6>
                <canvas id="forecastChart" height="120"></canvas>
            </div>

            <!-- INSIGHTS -->
            <div class="card card-premium p-4 mb-4">
                <h5 class="fw-bold mb-3">Key Insights</h5>
                <ul id="insightsList" class="list-unstyled mb-0">
                    <li>Loading insights...</li>
                </ul>
            </div>

            <!-- SUGGESTIONS WITH SEVERITY -->
            <div class="card card-premium p-4">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-lightbulb me-2 text-warning"></i>
                    Operational Suggestions
                </h5>
                <ul id="suggestionsList" class="list-unstyled mb-0">
                    <li>Loading suggestions...</li>
                </ul>
            </div>

        </div>

        <script>
            let chart;

            async function loadForecast() {
                const now = new Date();
                const month = now.getMonth() + 1;
                const year = now.getFullYear();
                const monthName = new Date(year, month - 1).toLocaleString('default', {
                    month: 'long'
                });
                document.getElementById('periodLabel').innerText = `${monthName} ${year}`;

                const forecastEndpoint = `https://bsis-03.keikaizen.xyz/journal/getMonthTotalRevenueForecast?month=${month}&year=${year}`;
                const historyEndpoint = `https://bsis-03.keikaizen.xyz/journal/getMonthsRevenueReport`;

                try {
                    const [forecastRes, historyRes] = await Promise.all([
                        fetch(forecastEndpoint),
                        fetch(historyEndpoint)
                    ]);

                    const forecast = await forecastRes.json();
                    const history = await historyRes.json();

                    // --- SUMMARY CARDS ---
                    document.getElementById('totalRevenue').innerText = '₱' + forecast.total_revenue.toFixed(2);
                    document.getElementById('totalTransactions').innerText = forecast.pharmacy_total_transactions;
                    document.getElementById('averageBill').innerText = '₱' + forecast.average_bill_amount.toFixed(2);
                    document.getElementById('revPerTxn').innerText =
                        '₱' + (forecast.total_revenue / forecast.pharmacy_total_transactions).toFixed(2);

                    // --- SUMMARY TEXT ---
                    document.getElementById('summaryText').innerHTML =
                        `For <strong>${monthName} ${year}</strong>, the hospital is expected to generate
                    <strong>₱${forecast.total_revenue.toFixed(2)}</strong> from
                    <strong>${forecast.pharmacy_total_transactions}</strong> transactions, with an
                    average bill amount of <strong>₱${forecast.average_bill_amount.toFixed(2)}</strong>.`;

                    // --- TREND CHART ---
                    const labels = history.map(h => new Date(h.year, h.month - 1).toLocaleString('default', {
                        month: 'short'
                    }));
                    const values = history.map(h => h.total_revenue);
                    labels.push(`${monthName} (Forecast)`);
                    values.push(forecast.total_revenue);

                    const ctx = document.getElementById('forecastChart');
                    if (chart) chart.destroy();

                    chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Total Revenue',
                                data: values,
                                fill: true,
                                tension: 0.35,
                                borderWidth: 3,
                                pointRadius: 6,
                                pointHoverRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => '₱' + ctx.raw.toFixed(2)
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        callback: value => '₱' + value
                                    }
                                }
                            }
                        }
                    });

                    // --- INSIGHTS ---
                    const insights = [];
                    const lastRevenue = history[history.length - 1]?.total_revenue || 0;
                    const growth = lastRevenue ? ((forecast.total_revenue - lastRevenue) / lastRevenue) * 100 : 0;
                    insights.push(growth > 0 ? `Projected revenue growth of ${growth.toFixed(2)}% compared to last month.` : `Projected revenue decline of ${Math.abs(growth).toFixed(2)}% compared to last month.`);
                    insights.push(forecast.pharmacy_total_transactions > 600 ? 'High patient transaction volume expected.' : 'Moderate patient transaction volume expected.');
                    document.getElementById('insightsList').innerHTML = insights.map(i => `<li>• ${i}</li>`).join('');

                    // --- SUGGESTIONS WITH SEVERITY ---
                    const suggestions = [];

                    const avgLast3 = history.slice(-3).reduce((a, b) => a + b.total_revenue, 0) / (history.slice(-3).length || 1);
                    const trendGrowth = avgLast3 ? ((forecast.total_revenue - avgLast3) / avgLast3) * 100 : 0;

                    function addSuggestion(text, severity) {
                        const className = severity === 'High' ? 'severity-high' : severity === 'Medium' ? 'severity-medium' : 'severity-low';
                        suggestions.push(`<li><span class="${className}">${severity}:</span> ${text}</li>`);
                    }

                    // Example rules
                    if (trendGrowth > 8) addSuggestion('Revenue is accelerating strongly. Consider expanding outpatient services.', 'High');
                    else if (trendGrowth > 0) addSuggestion('Revenue shows steady growth. Maintain service levels.', 'Medium');
                    else addSuggestion('Revenue projected to decline. Review patient acquisition strategies.', 'High');

                    if (forecast.pharmacy_total_transactions > 700) addSuggestion('High transaction volume expected. Ensure adequate staffing and inventory.', 'High');
                    else if (forecast.pharmacy_total_transactions > 400) addSuggestion('Moderate transaction volume expected. Optimize staff schedules.', 'Medium');
                    else addSuggestion('Low transaction volume expected. Consider marketing campaigns.', 'Low');

                    if (forecast.average_bill_amount > 2500) addSuggestion('Higher-than-usual average bill amount. Monitor patient affordability.', 'Medium');
                    else addSuggestion('Stable average bill amount. Maintain current pricing structure.', 'Low');

                    document.getElementById('suggestionsList').innerHTML = suggestions.join('');

                } catch (err) {
                    console.error(err);
                    alert('Failed to fetch revenue data. Make sure the API endpoints are running.');
                }
            }

            // --- PDF DOWNLOAD ---
            document.getElementById('downloadBtn').addEventListener('click', async () => {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF();

                const container = document.getElementById('dashboardContainer');
                await doc.html(container, {
                    x: 10,
                    y: 10,
                    width: 190,
                    windowWidth: container.scrollWidth
                });

                doc.save(`Hospital_Revenue_Forecast_${new Date().toISOString().split('T')[0]}.pdf`);
            });

            window.onload = loadForecast;
        </script>
    </div>
</body>

</html>