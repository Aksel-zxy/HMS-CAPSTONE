<?php
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Beds Distribution Summary Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

    <style>
        body {
            background: #f4f6f9;
        }

        .summary-card {
            border-radius: 12px;
        }

        .kpi-label {
            font-size: .85rem;
            color: #6c757d;
        }

        .kpi-value {
            font-size: 1.6rem;
            font-weight: 600;
        }

        .chart-card {
            border-radius: 12px;
            padding: 20px;
            background: #fff;
            height: 100%;
        }

        .insight-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            font-size: .9rem;
        }

        .back-btn {
            text-decoration: none;
        }

        .month-card {
            padding: 8px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
        }

        .month-card small {
            font-size: .8rem;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <!-- HEADER WITH YEAR SELECTOR -->
            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>
                    <h4 class="fw-semibold mb-0 mt-1">
                        Beds Distribution Summary Report
                    </h4>
                </div>

                <div class="d-flex align-items-center">
                    <select id="yearSelect" class="form-select form-select-sm me-2" style="width: 110px;">
                        <?php
                        $currentYear = date("Y");
                        for ($i = 2020; $i <= $currentYear + 2; $i++) {
                            echo "<option value='$i'>$i</option>";
                        }
                        ?>
                    </select>

                    <button class="btn btn-sm btn-primary" onclick="changeYear()">
                        Load
                    </button>
                </div>
            </div>

            <span class="text-muted" id="reportYear">Loading...</span>

            <!-- KPI CARDS -->
            <div class="row g-3 mb-4">

                <div class="col-md-3">
                    <div class="card summary-card p-3 text-center">
                        <div class="kpi-label">Total Beds</div>
                        <div class="kpi-value" id="totalBeds">0</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card summary-card p-3 text-center">
                        <div class="kpi-label">Occupied Beds (%)</div>
                        <div class="kpi-value text-primary" id="occupiedBeds">0%</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card summary-card p-3 text-center">
                        <div class="kpi-label">Available Beds (%)</div>
                        <div class="kpi-value text-success" id="availableBeds">0%</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card summary-card p-3 text-center">
                        <div class="kpi-label">Broken Beds (%)</div>
                        <div class="kpi-value text-danger" id="brokenBeds">0%</div>
                    </div>
                </div>

            </div>

            <!-- CHART + INSIGHTS -->
            <div class="row g-3">

                <!-- Pie Chart -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <h6 class="fw-semibold mb-3">Beds Distribution (Pie Chart)</h6>
                        <canvas id="bedsChart" height="260"></canvas>
                    </div>
                </div>

                <!-- INSIGHTS -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <h6 class="fw-semibold mb-3">Insights</h6>
                        <div class="insight-box" id="insightText">Loading insights...</div>
                    </div>
                </div>

            </div>

            <!-- Monthly Breakdown -->
            <div class="card p-3 shadow-sm mt-4">
                <h4 class="fw-semibold mb-3">Monthly Breakdown</h4>
                <div class="row g-3" id="monthlyCards"></div>
            </div>

        </div>
    </div>

    <script>
        // --- YEAR PARAMETER HANDLING ---
        const params = new URLSearchParams(window.location.search);
        let year = params.get("year");

        if (!year) {
            const thisYear = new Date().getFullYear();
            year = thisYear;
            history.replaceState(null, "", `?year=${year}`);
        }

        document.getElementById("yearSelect").value = year;
        document.getElementById("reportYear").innerText = year;

        function changeYear() {
            const selectedYear = document.getElementById("yearSelect").value;
            window.location.href = `?year=${selectedYear}`;
        }

        // --- MAIN LOGIC ---
        const endpoint = `https://localhost:7212/property/getYearBedsDistributionReport/${year}`;
        let bedsChart;

        async function loadReport() {
            const res = await fetch(endpoint);
            const data = await res.json();

            document.getElementById("totalBeds").innerText = data.total_beds;
            document.getElementById("occupiedBeds").innerText = data.occupied_beds.toFixed(2) + "%";
            document.getElementById("availableBeds").innerText = data.available_beds.toFixed(2) + "%";
            document.getElementById("brokenBeds").innerText = data.broken_beds.toFixed(2) + "%";

            renderPieChart(data);
            renderInsights(data);
            renderMonthlyCards(data.monthsAdmissionReport, year);
        }

        function renderPieChart(data) {
            const ctx = document.getElementById("bedsChart");

            const values = [
                data.occupied_beds,
                data.available_beds,
                data.broken_beds
            ];

            if (bedsChart) bedsChart.destroy();

            bedsChart = new Chart(ctx, {
                type: "doughnut",
                data: {
                    labels: ["Occupied", "Available", "Broken"],
                    datasets: [{
                        data: values,
                        backgroundColor: ["#0d6efd", "#198754", "#dc3545"],
                        hoverOffset: 6
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: "bottom"
                        },
                        datalabels: {
                            color: "#fff",
                            formatter: (value) => value.toFixed(1) + "%",
                            font: {
                                weight: "bold",
                                size: 14
                            }
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        }

        function renderInsights(data) {
            let insights = [];

            if (data.occupied_beds > 80)
                insights.push("High occupancy detected — capacity is heavily utilized.");

            if (data.occupied_beds < 40)
                insights.push("Low bed occupancy — good availability.");

            if (data.broken_beds > 10)
                insights.push("High number of broken beds — maintenance needed.");

            if (data.available_beds < 10)
                insights.push("Very few available beds — nearing full capacity.");

            if (insights.length === 0)
                insights.push("Bed usage is stable and within optimal range.");

            document.getElementById("insightText").innerHTML =
                "<ul><li>" + insights.join("</li><li>") + "</li></ul>";
        }

        function renderMonthlyCards(months, year) {
            const container = document.getElementById("monthlyCards");
            container.innerHTML = "";

            const names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

            for (let m = 1; m <= 12; m++) {
                const row = months.find(x => x.month === m) || {
                    occupied_beds: 0,
                    available_beds: 0,
                    broken_beds: 0,
                    total_beds: 0
                };

                container.innerHTML += `
                <div class="col-md-3 col-sm-6">
                    <div class="month-card p-2">
                        <h6 class="text-center">${names[m-1]}</h6>
                        <small>Total: ${row.total_beds}</small><br>
                        <small>Occ: ${row.occupied_beds}</small><br>
                        <small>Avail: ${row.available_beds}</small><br>
                        <small>Broken: ${row.broken_beds}</small><br>
                        <button class="btn btn-sm btn-info w-100 mt-1"
                            onclick="viewDetails(${m}, ${year})">View</button>
                    </div>
                </div>
            `;
            }
        }

        function viewDetails(month, year) {
            window.location.href =
                `patient_admission_and_summary.php?month=${month}&year=${year}`;
        }

        loadReport();
    </script>

</body>

</html>