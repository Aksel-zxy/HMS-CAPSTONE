<?php
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Patient Admission & Discharge Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

    <style>
        body {
            background-color: #f9f9f9;
            font-family: 'Segoe UI', sans-serif;
        }

        .summary-card {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }

        .summary-card h5 {
            color: #666;
            font-weight: 500;
        }

        .summary-card h2 {
            font-weight: 700;
            color: #007bff;
        }

        .chart-container {
            background-color: #fff;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .chart-wrapper {
            width: 60%;
            max-width: 350px;
            margin: 0 auto;
        }

        footer {
            text-align: center;
            margin-top: 40px;
            color: #777;
            font-size: 14px;
        }

        /* Insight Box */
        .insight-box {
            background: #ffffff;
            border-left: 5px solid #007bff;
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.07);
        }

        .insight-box h5 {
            font-weight: 700;
            color: #007bff;
        }
    </style>
</head>

<body class="p-4">
    <div class="d-flex">
        <?php include 'sidebar.php' ?>
        <div class="container">
            <h2 class="text-center mb-4"> Monthly Patient Admission & Discharge Summary</h2>

            <div class="row text-center mb-5" id="summaryCards">
                <div class="col mb-3">
                    <div class="card summary-card">
                        <div class="card-body">
                            <h5>Total Beds</h5>
                            <h2 id="totalBeds">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col mb-3">
                    <div class="card summary-card">
                        <div class="card-body">
                            <h5>Occupied Beds</h5>
                            <h2 id="occupiedBeds">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col mb-3">
                    <div class="card summary-card">
                        <div class="card-body">
                            <h5>Available Beds</h5>
                            <h2 id="availableBeds">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col mb-3">
                    <div class="card summary-card">
                        <div class="card-body">
                            <h5>Broken Beds</h5>
                            <h2 id="brokenBeds">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col mb-3">
                    <div class="card summary-card">
                        <div class="card-body">
                            <h5>Recently Discharged</h5>
                            <h2 id="dischargedCount">0</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="chart-container mb-5">
                <h5 class="text-center mb-3">Bed Utilization Breakdown</h5>
                <div class="chart-wrapper">
                    <canvas id="bedChart"></canvas>
                </div>
            </div>

            <!-- Insights Section -->
            <div class="insight-box" id="insightBox" style="display:none;">
                <h5>Insights</h5>
                <p id="insightText"></p>
            </div>

        </div>
    </div>

    <footer>© 2025 Hospital Dashboard — Powered by Bootstrap & Chart.js</footer>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const month = urlParams.get('month');
        const year = urlParams.get('year');

        let bedChart;

        if (month && year) {
            const apiUrl = `https://localhost:7212/property/getMonthSummaryAdmissionAndDischargeReport?month=${month}&year=${year}`;
            fetch(apiUrl)
                .then(res => res.json())
                .then(data => {
                    const totalBeds = data.total_beds || 0;
                    const occupied = data.occupied_beds || 0;
                    const available = data.available_beds || 0;
                    const broken = data.broken_beds || 0;
                    const discharged = data.recently_discharged || 0;

                    document.getElementById("totalBeds").innerText = totalBeds;
                    document.getElementById("occupiedBeds").innerText = occupied;
                    document.getElementById("availableBeds").innerText = available;
                    document.getElementById("brokenBeds").innerText = broken;
                    document.getElementById("dischargedCount").innerText = discharged;

                    const ctx = document.getElementById('bedChart').getContext('2d');
                    const total = occupied + available + broken;

                    // Chart
                    bedChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: ['Occupied Beds', 'Available Beds', 'Broken Beds'],
                            datasets: [{
                                data: [occupied, available, broken],
                                backgroundColor: ['#007bff', '#28a745', '#dc3545'],
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                datalabels: {
                                    color: '#fff',
                                    font: {
                                        weight: 'bold',
                                        size: 14
                                    },
                                    formatter: (value) => {
                                        return total ? ((value / total) * 100).toFixed(1) + "%" : "0%";
                                    }
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });

                    // =============================
                    // Generate Insights
                    // =============================
                    let insights = [];

                    // Occupancy level
                    const occupancyRate = total ? (occupied / total) * 100 : 0;
                    if (occupancyRate >= 85) {
                        insights.push(`High bed occupancy detected (${occupancyRate.toFixed(1)}%). Hospital capacity is heavily utilized.`);
                    } else if (occupancyRate >= 50) {
                        insights.push(`Moderate occupancy at ${occupancyRate.toFixed(1)}%. Hospital is operating at a stable level.`);
                    } else {
                        insights.push(`Low occupancy at ${occupancyRate.toFixed(1)}%. There is significant bed availability.`);
                    }

                    // Broken beds
                    if (broken > 5) {
                        insights.push(`There are ${broken} broken beds. Maintenance is urgently needed.`);
                    } else if (broken > 0) {
                        insights.push(`${broken} beds require maintenance attention.`);
                    }

                    // Discharge trend
                    if (discharged >= 10) {
                        insights.push(`High number of recent discharges (${discharged}). Patient turnover is strong this month.`);
                    } else if (discharged >= 3) {
                        insights.push(`Moderate discharge count: ${discharged}.`);
                    } else {
                        insights.push(`Low discharge activity this month (${discharged}).`);
                    }

                    // Available beds
                    if (available === 0) {
                        insights.push(`No available beds remaining. Admission capacity is fully saturated.`);
                    } else if (available <= 5) {
                        insights.push(`Only ${available} beds available. Hospital nearing full capacity.`);
                    } else {
                        insights.push(`${available} beds are currently available for admission.`);
                    }

                    // Render Insight Text
                    document.getElementById("insightText").innerHTML = insights.join("<br>");
                    document.getElementById("insightBox").style.display = "block";

                })
                .catch(err => console.error(err));
        }
    </script>

</body>

</html>