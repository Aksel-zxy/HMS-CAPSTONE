<?
include 'header.php'
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Operational Cost Forecast Dashboard</title>

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
    </style>
</head>

<body>
    <div class="d-flex">

        <?
        include 'sidebar.php'
        ?>

        <div class="container py-5">

            <!-- HEADER -->
            <div class="mb-4 text-center">
                <h2 class="fw-bold">
                    <i class="bi bi-gear-wide-connected me-2"></i>
                    Operational Cost Forecast
                </h2>
                <p class="text-muted mb-0">
                    Historical operational cost trend and AI-driven forecast
                </p>
                <button class="btn btn-danger mt-3" id="downloadBtn">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                </button>
            </div>

            <!-- SUMMARY TEXT -->
            <div class="card card-premium p-4 mb-4">
                <p id="summaryText" class="summary-text mb-0">
                    Loading operational cost forecast...
                </p>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card card-premium p-3 text-center">
                        <i class="bi bi-calendar-event stat-icon text-primary mb-2"></i>
                        <div class="stat-value" id="forecastMonth">—</div>
                        <div class="stat-label">Forecasted Month</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-premium p-3 text-center">
                        <i class="bi bi-cash-coin stat-icon text-danger mb-2"></i>
                        <div class="stat-value" id="forecastCost">—</div>
                        <div class="stat-label">Forecasted Operational Cost</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-premium p-3 text-center">
                        <i class="bi bi-graph-up-arrow stat-icon text-success mb-2"></i>
                        <div class="stat-value" id="forecastDelta">—</div>
                        <div class="stat-label">Change vs Last Month</div>
                    </div>
                </div>
            </div>

            <!-- TREND CHART -->
            <div class="chart-container mb-4">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-graph-up-arrow me-1"></i>
                    Operational Cost Trend (Historical + Forecast)
                </h6>
                <canvas id="costChart" height="120"></canvas>
            </div>

            <!-- INSIGHTS & SUGGESTIONS -->
            <div class="card card-premium p-4">
                <h5 class="fw-bold mb-3">Key Insights & Suggestions</h5>
                <ul id="insightsList" class="list-unstyled mb-0">
                    <li>Loading insights...</li>
                </ul>
            </div>

        </div>

        <script>
            let chart;

            const forecastUrl =
                "https://localhost:7212/journal/getMonthCostManagementForecast";

            const historyUrl =
                "https://localhost:7212/journal/getPreviousMonthsCostManagement";

            const API_KEY = "EZEKIEL_KLAYSKIE";

            async function loadOperationalCostForecast() {
                try {
                    const [forecastRes, historyRes] = await Promise.all([
                        fetch(forecastUrl, {
                            headers: {
                                "X-API-KEY": API_KEY,
                                "Content-Type": "application/json"
                            }
                        }),
                        fetch(historyUrl, {
                            headers: {
                                "X-API-KEY": API_KEY,
                                "Content-Type": "application/json"
                            }
                        })
                    ]);

                    if (!forecastRes.ok || !historyRes.ok) throw new Error("Failed to fetch cost data.");

                    const forecast = await forecastRes.json();
                    const history = await historyRes.json();

                    const monthName = new Date(forecast.year, forecast.month - 1)
                        .toLocaleString("default", {
                            month: "long"
                        });

                    // Summary cards
                    document.getElementById("forecastMonth").innerText = `${monthName} ${forecast.year}`;
                    document.getElementById("forecastCost").innerText = "₱" + forecast.month_forecasted_cost.toLocaleString();

                    const lastActual = history[history.length - 1]?.total_month_operational_cost || 0;

                    const delta = forecast.month_forecasted_cost - lastActual;
                    const deltaPercent = lastActual ? ((delta / lastActual) * 100).toFixed(2) : 0;

                    const deltaEl = document.getElementById("forecastDelta");
                    deltaEl.innerText = `${delta >= 0 ? "+" : ""}₱${delta.toLocaleString()} (${deltaPercent}%)`;
                    deltaEl.classList.remove("text-success", "text-danger");
                    deltaEl.classList.add(delta >= 0 ? "text-success" : "text-danger");

                    // Summary text
                    document.getElementById("summaryText").innerHTML =
                        `For <strong>${monthName} ${forecast.year}</strong>, operational expenses are projected to reach 
                    <strong>₱${forecast.month_forecasted_cost.toLocaleString()}</strong>. 
                    This represents a 
                    <strong>${delta >= 0 ? "increase" : "decrease"}</strong> of 
                    <strong>₱${Math.abs(delta).toLocaleString()}</strong> 
                    (${Math.abs(deltaPercent)}%) compared to the previous month. 
                    ${delta >= 0
                        ? "Cost pressures may require tighter budget controls or efficiency initiatives."
                        : "Lower projected costs suggest improved operational efficiency or reduced spending."}`;

                    // Trend chart
                    const labels = history.map(h =>
                        new Date(h.year, h.month - 1).toLocaleString("default", {
                            month: "short"
                        })
                    );
                    const values = history.map(h => h.total_month_operational_cost);

                    labels.push(`${monthName} (Forecast)`);
                    values.push(forecast.month_forecasted_cost);

                    const ctx = document.getElementById("costChart");
                    if (chart) chart.destroy();

                    chart = new Chart(ctx, {
                        type: "line",
                        data: {
                            labels,
                            datasets: [{
                                label: "Operational Cost",
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
                                        label: ctx => "₱" + ctx.raw.toLocaleString()
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        callback: value => "₱" + value.toLocaleString()
                                    }
                                }
                            }
                        }
                    });

                    // ✅ Key Insights & Suggestions
                    const insights = [];

                    insights.push(delta >= 0 ?
                        `Projected cost growth of ${deltaPercent}% compared to last month.` :
                        `Projected cost reduction of ${Math.abs(deltaPercent)}% compared to last month.`);

                    insights.push(forecast.month_forecasted_cost > lastActual ?
                        "Operational expenses are trending upward, indicating rising cost pressures." :
                        "Operational expenses are trending downward, indicating improved cost efficiency.");

                    insights.push(forecast.month_forecasted_cost > 500000 ?
                        "High operational cost level may require executive budget review." :
                        "Operational cost remains within a moderate and manageable range.");

                    // Additional actionable suggestions
                    insights.push("Maintain current efficiency measures and track savings.");
                    if (delta >= 0) insights.push("Increase efficiency initiatives and monitor high-cost areas closely.");
                    if (forecast.month_forecasted_cost > 500000) insights.push("Review budget allocation and consider cost-cutting measures.");
                    insights.push("Review efficiency measures regularly to control operational costs.");

                    document.getElementById("insightsList").innerHTML =
                        insights.map(i => `<li>• ${i}</li>`).join("");

                } catch (err) {
                    console.error(err);
                    alert("Failed to fetch operational cost data. Make sure the API endpoints are running.");
                }
            }

            // ✅ PDF Export
            document.getElementById("downloadBtn").addEventListener("click", async () => {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF("p", "mm", "a4");
                const container = document.body;

                const canvas = document.getElementById("costChart");
                const chartImg = canvas.toDataURL("image/png");

                await doc.html(container, {
                    x: 10,
                    y: 10,
                    width: 190,
                    callback: function(doc) {
                        doc.addPage();
                        doc.setFontSize(14);
                        doc.text("Operational Cost Trend", 14, 20);
                        doc.addImage(chartImg, "PNG", 10, 25, 180, 90);
                        doc.save(`Operational_Cost_Forecast_${new Date().toISOString().split("T")[0]}.pdf`);
                    }
                });
            });

            window.onload = loadOperationalCostForecast;
        </script>
    </div>
</body>

</html>