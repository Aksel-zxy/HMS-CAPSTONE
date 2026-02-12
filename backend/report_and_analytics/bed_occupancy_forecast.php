<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Bed Occupancy Forecast</title>

    <!-- Premium Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', sans-serif;
        }

        .card-premium {
            border: none;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .08);
        }

        .stat-icon {
            font-size: 28px;
            opacity: .8;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
        }

        .disclaimer {
            font-size: 14px;
            color: #6c757d;
        }

        .summary-text {
            font-size: 16px;
            color: #495057;
            line-height: 1.6;
        }
    </style>
</head>

<body>

    <div class="container py-5">

        <!-- HEADER -->
        <div class="mb-4 text-center">
            <h2 class="fw-bold">
                <i class="bi bi-hospital me-2"></i>
                Monthly Bed Occupancy Forecast
            </h2>
            <p class="text-muted mb-0">
                Historical trend and AI-driven bed utilization forecast
            </p>
            <p id="forecastPeriod" class="text-muted mb-0"></p>
        </div>

        <!-- SUMMARY TEXT -->
        <div class="card card-premium p-4 mb-4">
            <p id="summaryText" class="summary-text mb-0">
                Loading bed occupancy forecast...
            </p>
        </div>

        <!-- STATS -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card card-premium p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">Occupied Beds</div>
                            <div class="stat-value" id="occupiedBeds">—</div>
                        </div>
                        <i class="bi bi-hospital stat-icon text-primary"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-premium p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">Discharged</div>
                            <div class="stat-value" id="discharged">—</div>
                        </div>
                        <i class="bi bi-box-arrow-right stat-icon text-success"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-premium p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">Occupancy Rate</div>
                            <div class="stat-value" id="occupancyRate">—%</div>
                        </div>
                        <i class="bi bi-speedometer2 stat-icon text-warning"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-premium p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">Broken Bed Rate</div>
                            <div class="stat-value" id="brokenRate">—%</div>
                        </div>
                        <i class="bi bi-tools stat-icon text-danger"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- TREND CHART -->
        <div class="card card-premium p-4 mb-4">
            <h6 class="fw-bold mb-3">
                <i class="bi bi-graph-up-arrow me-1"></i>
                Bed Occupancy Trend (Historical + Forecast)
            </h6>
            <canvas id="forecastChart" height="120"></canvas>
        </div>

        <!-- INSIGHTS -->
        <div class="card card-premium p-4">
            <h5 class="fw-bold mb-3">Key Insights</h5>
            <ul id="insightsList" class="list-unstyled mb-0">
                <li>Loading insights...</li>
            </ul>
        </div>

    </div>

    <script>
        let chart;

        async function loadForecast() {
            const now = new Date();
            const month = now.getMonth() + 1;
            const year = now.getFullYear();

            document.getElementById('forecastPeriod').innerHTML =
                `Forecast for <strong>${now.toLocaleString('default', { month: 'long' })} ${year}</strong>`;

            try {
                const [forecastRes, historyRes] = await Promise.all([
                    fetch(`https://localhost:7212/property/getMonthForecastResult?month=${month}&year=${year}`),
                    fetch(`https://localhost:7212/property/getMonthsOccupiedBeds`)
                ]);

                const forecast = await forecastRes.json();
                const history = await historyRes.json();

                // SUMMARY STATS
                document.getElementById('occupiedBeds').innerText =
                    forecast.predicted_occupied_beds;

                document.getElementById('discharged').innerText =
                    forecast.predicted_recently_discharged;

                document.getElementById('occupancyRate').innerText =
                    forecast.predicted_bed_occupancy_rate.toFixed(2) + '%';

                document.getElementById('brokenRate').innerText =
                    forecast.predicted_broken_bed_rate.toFixed(2) + '%';

                // SUMMARY TEXT
                const lastMonthBeds =
                    history[history.length - 1]?.total_occupied_beds || 0;

                const growth = lastMonthBeds ?
                    ((forecast.predicted_occupied_beds - lastMonthBeds) / lastMonthBeds) * 100 :
                    0;

                const monthName = new Date(year, month - 1)
                    .toLocaleString('default', {
                        month: 'long'
                    });

                document.getElementById('summaryText').innerHTML =
                    `For <strong>${monthName} ${year}</strong>, the hospital is projected to have
                     <strong>${forecast.predicted_occupied_beds}</strong> occupied beds and
                     <strong>${forecast.predicted_recently_discharged}</strong> patient discharges.
                     Bed occupancy is expected to reach
                     <strong>${forecast.predicted_bed_occupancy_rate.toFixed(2)}%</strong>,
                     indicating ${forecast.predicted_bed_occupancy_rate >= 85
                        ? 'critical capacity pressure requiring immediate intervention.'
                        : forecast.predicted_bed_occupancy_rate >= 70
                            ? 'high utilization that may strain hospital resources.'
                            : 'manageable bed utilization levels.'}
                     This reflects a ${growth >= 0 ? 'growth' : 'decline'} of
                     <strong>${Math.abs(growth).toFixed(2)}%</strong> compared to last month.`;

                renderPredictionChart(history, forecast);
                renderInsights(history, forecast);

            } catch (err) {
                console.error(err);
                alert('Failed to fetch bed occupancy data. Make sure the API endpoints are running.');
            }
        }

        function renderPredictionChart(history, forecast) {
            const labels = history.map(h =>
                new Date(h.year, h.month - 1).toLocaleString('default', {
                    month: 'short'
                })
            );

            const values = history.map(h => h.total_occupied_beds);

            const forecastMonthLabel =
                new Date().toLocaleString('default', {
                    month: 'short'
                }) + ' (Forecast)';

            labels.push(forecastMonthLabel);
            values.push(forecast.predicted_occupied_beds);

            const ctx = document.getElementById('forecastChart');
            if (chart) chart.destroy();

            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Occupied Beds',
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
                                label: ctx => `${ctx.raw} beds`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: value => value + ' beds'
                            }
                        }
                    }
                }
            });
        }

        function renderInsights(history, forecast) {
            const insights = [];

            const lastMonthBeds =
                history[history.length - 1]?.total_occupied_beds || 0;

            const growth = lastMonthBeds ?
                ((forecast.predicted_occupied_beds - lastMonthBeds) / lastMonthBeds) * 100 :
                0;

            insights.push(
                growth > 0 ?
                `Projected bed occupancy growth of ${growth.toFixed(2)}% compared to last month.` :
                `Projected bed occupancy decline of ${Math.abs(growth).toFixed(2)}% compared to last month.`
            );

            insights.push(
                forecast.predicted_bed_occupancy_rate >= 85 ?
                'Critical utilization expected. Immediate capacity expansion or patient diversion may be required.' :
                forecast.predicted_bed_occupancy_rate >= 70 ?
                'High bed utilization expected. Staffing and discharge planning should be optimized.' :
                'Bed utilization is projected to remain within manageable levels.'
            );

            insights.push(
                forecast.predicted_broken_bed_rate > 5 ?
                'Elevated broken bed rate may further constrain available capacity.' :
                'Broken bed rate is within acceptable operational limits.'
            );

            document.getElementById('insightsList').innerHTML =
                insights.map(i => `<li>• ${i}</li>`).join('');
        }

        window.onload = loadForecast;
    </script>

</body>

</html>