<?php
include 'header.php';
?>
<!DOCTYPE html>
<html>

<head>
    <title>Patient Census Report & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Layout wrapper */
        .layout-wrapper {
            display: flex;
            width: 100%;
        }

        /* Main content */
        #mainContent {
            flex-grow: 1;
            padding: 20px;
        }

        /* Drawer button */
        #openInsightsBtn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 14px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Slide-in Drawer */
        #aiDrawer {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background: #fff;
            border-left: 2px solid #ddd;
            padding: 25px;
            overflow-y: auto;
            transition: right 0.3s ease;
            z-index: 10000;
            box-shadow: -4px 0px 15px rgba(0, 0, 0, 0.15);
        }

        #aiDrawer.open {
            right: 0;
        }

        #drawerClose {
            cursor: pointer;
            font-size: 20px;
            float: right;
            color: #444;
        }

        /* Mobile responsive */
        @media (max-width: 992px) {
            #aiDrawer {
                width: 100%;
                right: -100%;
            }

            #aiDrawer.open {
                right: 0;
            }
        }
    </style>
</head>

<body class="bg-light">

    <div class="layout-wrapper">

        <!-- LEFT SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div id="mainContent">
            <div class="container-fluid py-4">

                <h2 class="text-center mb-4 fw-bold">Patient Census Report & Analytics</h2>

                <!-- FILTERS -->
                <div class="card p-3 mb-4 shadow-sm">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" id="startDate" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" id="endDate" class="form-control">
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-primary w-100" onclick="loadAll()">Generate Report</button>
                        </div>
                    </div>
                </div>

                <!-- KPI CARDS -->
                <div class="row mb-4" id="kpiCards"></div>

                <!-- TABLE -->
                <div class="card p-3 mb-4 shadow-sm">
                    <h5 class="fw-bold mb-3">Census Records</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="censusTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Assignment ID</th>
                                    <th>Patient</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Bed</th>
                                    <th>Assigned</th>
                                    <th>Released</th>
                                    <th>Condition</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- CHARTS -->
                <div class="row g-4">

                    <div class="col-md-6">
                        <div class="card p-3 shadow-sm">
                            <h6 class="fw-bold">Age Distribution</h6>
                            <canvas id="ageChart"></canvas>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card p-3 shadow-sm">
                            <h6 class="fw-bold">Gender Distribution</h6>
                            <div style="height: 250px;">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card p-3 shadow-sm">
                            <h6 class="fw-bold">Conditions</h6>
                            <canvas id="conditionChart"></canvas>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card p-3 shadow-sm">
                            <h6 class="fw-bold">Bed Occupancy</h6>
                            <canvas id="bedChart"></canvas>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="card p-3 shadow-sm">
                            <h6 class="fw-bold">Monthly Admissions</h6>
                            <div style="height: 280px;">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>

    <!-- AI INSIGHTS DRAWER -->
    <button id="openInsightsBtn" class="btn btn-info">AI</button>

    <div id="aiDrawer">
        <span id="drawerClose" onclick="toggleInsights()">✖</span>
        <h4 class="fw-bold mt-3">AI Insights</h4>
        <hr>
        <p id="aiInsights" class="text-secondary">Run a report to generate insights...</p>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        const API_BASE = "https://bsis-03.keikaizen.xyz";
        let charts = [];

        /* Drawer Toggle */
        document.getElementById("openInsightsBtn").onclick = toggleInsights;

        function toggleInsights() {
            document.getElementById("aiDrawer").classList.toggle("open");
        }

        function destroyCharts() {
            charts.forEach(c => c.destroy());
            charts = [];
        }

        async function loadAll() {
            const start = document.getElementById("startDate").value;
            const end = document.getElementById("endDate").value;

            if (!start || !end) {
                alert("Please select a date range.");
                return;
            }

            await loadCensus(start, end);
            const analytics = await loadAnalytics(start, end);

            if (analytics) generateAIInsights(analytics);
        }


        /* TABLE */
        async function loadCensus(start, end) {
            const res = await fetch(`${API_BASE}/patient/getCensus?startDate=${start}&endDate=${end}`);
            const data = await res.json();

            const tbody = document.querySelector("#censusTable tbody");
            tbody.innerHTML = "";

            if (!Array.isArray(data)) {
                tbody.innerHTML = `<tr><td colspan='8' class='text-center'>No data found</td></tr>`;
                return;
            }

            data.forEach(row => {
                tbody.innerHTML += `
                <tr>
                    <td>${row.assignment_id}</td>
                    <td>${row.fname} ${row.lname}</td>
                    <td>${row.age}</td>
                    <td>${row.gender}</td>
                    <td>${row.bed_id}</td>
                    <td>${row.assigned_date.split("T")[0]}</td>
                    <td>${row.released_date ? row.released_date.split("T")[0] : "Active"}</td>
                    <td>${row.condition_name ?? "N/A"}</td>
                </tr>`;
            });
        }


        /* ANALYTICS */
        async function loadAnalytics(start, end) {
            destroyCharts();

            const res = await fetch(`${API_BASE}/patient/GetAdvancedAnalytics?startDate=${start}&endDate=${end}`);
            const d = await res.json();

            if (!d || d.message) return null;

            document.getElementById("kpiCards").innerHTML = `
                ${kpi("Total Patients", d.totalPatients, "primary")}
                ${kpi("Active", d.activePatients, "success")}
                ${kpi("Discharged", d.dischargedPatients, "warning")}
                ${kpi("Avg Age", d.avgAge.toFixed(1), "info")}
                ${kpi("Avg LOS", d.losAverage.toFixed(1) + " days", "dark")}
            `;

            charts.push(buildBar("ageChart",
                d.ageDistribution.map(x => x.age),
                d.ageDistribution.map(x => x.count)
            ));

            charts.push(buildPie("genderChart",
                ["Male", "Female"],
                [d.male, d.female]
            ));

            charts.push(buildBar("conditionChart",
                d.conditions.map(x => x.condition),
                d.conditions.map(x => x.count)
            ));

            charts.push(buildBar("bedChart",
                d.bedUsage.map(x => "Bed " + x.bed),
                d.bedUsage.map(x => x.count)
            ));

            charts.push(buildLine("monthlyChart",
                d.admissionsPerMonth.map(x => x.month),
                d.admissionsPerMonth.map(x => x.count)
            ));

            return d;
        }

        /* AI INSIGHTS */
        function generateAIInsights(d) {

            let topCondition = d.conditions.length > 0 ?
                d.conditions.sort((a, b) => b.count - a.count)[0].condition :
                "No recorded conditions";

            let busiestBed = d.bedUsage.length > 0 ?
                d.bedUsage.sort((a, b) => b.count - a.count)[0].bed :
                "N/A";

            let busiestMonth = d.admissionsPerMonth.length > 0 ?
                d.admissionsPerMonth.sort((a, b) => b.count - a.count)[0].month :
                "N/A";

            document.getElementById("aiInsights").innerHTML = `
                <b>📊 Summary</b><br>
                • Total Patients: <b>${d.totalPatients}</b><br>
                • Active: <b>${d.activePatients}</b><br>
                • Discharged: <b>${d.dischargedPatients}</b><br>
                • Avg LOS: <b>${d.losAverage.toFixed(1)} days</b><br><br>

                <b>🩺 Health Insights</b><br>
                • Most common condition: <b>${topCondition}</b><br>
                • Peak admissions month: <b>${busiestMonth}</b><br><br>

                <b>🛏 Bed Utilization</b><br>
                • Busiest bed: <b>${busiestBed}</b><br><br>

                <b>🤖 Recommendations</b><br>
                • Check long-stay patients for care optimization.<br>
                • Prepare staff & resources for <b>${busiestMonth}</b> (peak period).<br>
                • Prioritize screenings for <b>${topCondition}</b> cases.<br>
                • Monitor turnover for Bed <b>${busiestBed}</b> to avoid delays.<br>
            `;
        }

        /* CHART HELPERS */
        function kpi(title, value, color) {
            return `
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-${color}">
                        <div class="card-body">
                            <h6>${title}</h6>
                            <h2>${value}</h2>
                        </div>
                    </div>
                </div>`;
        }

        function buildPie(id, labels, values) {
            return new Chart(document.getElementById(id), {
                type: "pie",
                data: {
                    labels,
                    datasets: [{
                        data: values
                    }]
                }
            });
        }

        function buildBar(id, labels, values) {
            return new Chart(document.getElementById(id), {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                        data: values
                    }]
                }
            });
        }

        function buildLine(id, labels, values) {
            return new Chart(document.getElementById(id), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        fill: false,
                        tension: 0.3
                    }]
                }
            });
        }
    </script>

</body>

</html>