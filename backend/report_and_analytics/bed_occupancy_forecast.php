<?
include 'header.php'
?>
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

            <!-- FORECAST CHART -->
            <div class="card card-premium p-4 mb-4">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-graph-up-arrow me-1"></i>
                    Bed Occupancy Forecast
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
                    const response = await fetch(`https://bsis-03.keikaizen.xyz/property/getMonthForecastResult?month=2&year=2026`);
                    const forecast = await response.json();

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
                    document.getElementById('summaryText').innerHTML =
                        `The forecast predicts <strong>${forecast.predicted_occupied_beds}</strong> occupied beds and
                     <strong>${forecast.predicted_recently_discharged}</strong> discharges. Bed occupancy rate is expected
                     to reach <strong>${forecast.predicted_bed_occupancy_rate.toFixed(2)}%</strong>, indicating
                     ${forecast.predicted_bed_occupancy_rate >= 85
                        ? 'critical capacity levels requiring urgent intervention.'
                        : forecast.predicted_bed_occupancy_rate >= 70
                            ? 'high utilization that may strain resources.'
                            : 'manageable utilization levels.'}`;

                    renderPredictionChart(forecast);
                    renderInsights(forecast);

                } catch (err) {
                    console.error(err);
                    alert('Failed to fetch bed occupancy data. Make sure the API is reachable.');
                }
            }

            function renderPredictionChart(forecast) {
                const labels = ['Forecast'];
                const values = [forecast.predicted_occupied_beds];

                const ctx = document.getElementById('forecastChart');

                if (chart) chart.destroy();

                chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Occupied Beds (Forecast)',
                            data: values,
                            backgroundColor: '#0d6efd'
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

            function renderInsights(forecast) {
                const insights = [];

                insights.push(
                    forecast.predicted_bed_occupancy_rate >= 85 ?
                    'Critical bed pressure expected — consider surge capacity plans.' :
                    forecast.predicted_bed_occupancy_rate >= 70 ?
                    'High bed load projected — monitor discharges closely.' :
                    'Bed utilization expected to remain stable.'
                );

                insights.push(
                    forecast.predicted_broken_bed_rate > 5 ?
                    'Broken bed rate may reduce usable capacity — maintenance required.' :
                    'Broken bed rate is within normal operational limits.'
                );

                document.getElementById('insightsList').innerHTML =
                    insights.map(i => `<li>• ${i}</li>`).join('');
            }

            window.onload = loadForecast;
        </script>
    </div>
</body>

</html>