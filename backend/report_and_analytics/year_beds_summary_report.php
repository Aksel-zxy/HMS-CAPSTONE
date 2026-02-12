<?php
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beds Distribution Summary Report</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <style>
        /* MONTHLY BOXES */
        .month-card {
            padding: 6px !important;
            font-size: 12px;
        }

        .month-card h6 {
            font-size: 13px;
            margin-bottom: 4px;
        }

        .month-card small {
            display: block;
            font-size: 11px;
            line-height: 1.2;
        }

        .month-chart {
            height: 80px !important;
        }

        /* PIE CHART SMALLER */
        #pieChart {
            max-height: 400px !important;
        }
    </style>

</head>

<body class="bg-light">
    <div class="d-flex">
        <?php
        include 'sidebar.php'
        ?>
        <div class="container py-4">
            <h2 class="mb-4 text-center">Beds Distribution - Yearly Summary</h2>

            <!-- Year Selector -->
            <form id="yearForm" class="card p-3 shadow-sm mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Select Year</label>
                        <select id="yearInput" class="form-select">
                            <?php
                            $currentYear = date("Y");
                            for ($i = 2020; $i <= $currentYear + 2; $i++) {
                                echo "<option value='$i'>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" onclick="loadReport()" class="btn btn-primary mt-4 px-4">Load Report</button>
                    </div>
                </div>
            </form>

            <!-- SUMMARY -->
            <div id="reportSection" class="d-none">
                <div class="card p-3 shadow-sm mb-4">
                    <h4 class="mb-3">Yearly Summary</h4>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h6>Total Beds</h6>
                            <p class="fs-4 fw-bold" id="totalBeds"></p>
                        </div>
                        <div class="col-md-3">
                            <h6>Occupied Beds (%)</h6>
                            <p class="fs-4 fw-bold text-primary" id="occupiedBeds"></p>
                        </div>
                        <div class="col-md-3">
                            <h6>Available Beds (%)</h6>
                            <p class="fs-4 fw-bold text-success" id="availableBeds"></p>
                        </div>
                        <div class="col-md-3">
                            <h6>Broken Beds (%)</h6>
                            <p class="fs-4 fw-bold text-danger" id="brokenBeds"></p>
                        </div>
                    </div>
                </div>

                <!-- MONTHLY BREAKDOWN AND PIE CHART SIDE BY SIDE -->
                <div class="row">
                    <!-- Monthly Breakdown -->
                    <div class="col-lg-8">
                        <div class="card p-3 shadow-sm mb-4">
                            <h4 class="mb-3">Monthly Breakdown</h4>
                            <div class="row g-2" id="monthlyCards"></div>
                        </div>
                    </div>

                    <!-- Year-Level Pie Chart -->
                    <div class="col-lg-4">
                        <div class="card p-3 shadow-sm mb-4">
                            <h5 class="text-center">Beds Distribution (Pie Chart)</h5>
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        let pieChart;

        function loadReport() {
            const year = document.getElementById("yearInput").value;
            const endpoint = `https://localhost:7212/property/getYearBedsDistributionReport/${year}`;

            fetch(endpoint)
                .then(res => res.json())
                .then(data => {
                    document.getElementById("reportSection").classList.remove("d-none");
                    document.getElementById("totalBeds").innerText = data.total_beds;
                    document.getElementById("occupiedBeds").innerText = data.occupied_beds.toFixed(2);
                    document.getElementById("availableBeds").innerText = data.available_beds.toFixed(2);
                    document.getElementById("brokenBeds").innerText = data.broken_beds.toFixed(2);

                    renderMonthlyCards(data.monthsAdmissionReport, year);
                    updatePieChart(data);
                })
                .catch(err => {
                    alert("Failed to load data.");
                    console.error(err);
                });
        }

        /* MONTHLY SUMMARY BOXES WITH BAR CHARTS AND VIEW BUTTONS */
        function renderMonthlyCards(apiMonths, year) {
            const container = document.getElementById("monthlyCards");
            container.innerHTML = "";
            const monthNames = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

            const months = [];
            for (let i = 1; i <= 12; i++) {
                const found = apiMonths?.find(m => m.month === i);
                months.push(found ?? {
                    month: i,
                    total_beds: 0,
                    occupied_beds: 0,
                    available_beds: 0,
                    broken_beds: 0,
                    recently_discharged: 0
                });
            }

            months.forEach((m, index) => {
                const chartId = "monthChart_" + index;

                container.innerHTML += `
        <div class="col-lg-3 col-md-4 col-sm-6 col-6">
            <div class="card shadow-sm month-card">
                <h6 class="text-center">${monthNames[m.month]}</h6>
                <small><b>Total:</b> ${m.total_beds}</small>
                <small><b>Occ:</b> ${m.occupied_beds}</small>
                <small><b>Avail:</b> ${m.available_beds}</small>
                <small><b>Broken:</b> ${m.broken_beds}</small>
                <canvas id="${chartId}" class="month-chart mb-1"></canvas>
                <button class="btn btn-sm btn-info w-100" onclick="viewDetails(${m.month}, ${year})">View</button>
            </div>
        </div>
        `;

                // Small bar chart inside the month card
                setTimeout(() => {
                    new Chart(document.getElementById(chartId), {
                        type: "bar",
                        data: {
                            labels: ["Occ", "Avail", "Broken"],
                            datasets: [{
                                data: [m.occupied_beds, m.available_beds, m.broken_beds],
                                backgroundColor: ["#0d6efd", "#198754", "#dc3545"]
                            }]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    anchor: "end",
                                    align: "top",
                                    formatter: v => v,
                                    font: {
                                        size: 9,
                                        weight: "bold"
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        },
                        plugins: [ChartDataLabels]
                    });
                }, 100);
            });
        }

        /* PIE CHART ONLY */
        function updatePieChart(data) {
            const values = [data.occupied_beds, data.available_beds, data.broken_beds];
            const labels = ["Occupied", "Available", "Broken"];
            const total = values.reduce((a, b) => a + b, 0);

            if (pieChart) pieChart.destroy();

            pieChart = new Chart(document.getElementById("pieChart"), {
                type: "pie",
                data: {
                    labels,
                    datasets: [{
                        data: values
                    }]
                },
                options: {
                    plugins: {
                        datalabels: {
                            color: "#fff",
                            formatter: v => ((v / total) * 100).toFixed(1) + "%",
                            font: {
                                weight: "bold",
                                size: 12
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                },
                plugins: [ChartDataLabels]
            });
        }

        /* NAVIGATE TO DETAIL PAGE WITH QUERY PARAMS */
        function viewDetails(month, year) {
            const viewUrl = `http://localhost:8080/backend/report_and_analytics/patient_admission_and_summary.php?month=${month}&year=${year}`;
            window.location.href = viewUrl;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>