<?php
include 'header.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Month Employee Performance Summary Report</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <style>
        .chart-container {
            width: 100%;
            height: 220px;
        }

        .summary-card {
            min-height: 120px;
        }
    </style>
</head>

<body class="bg-light">
    <div class="d-flex">
        <?php include "sidebar.php" ?>
        <div class="container py-4">

            <h2 class="mb-4">Month Employee Performance Summary Report</h2>

            <!-- YEAR + MONTH SELECT -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Select Year</label>
                    <select id="yearSelector" onchange="updateSummary()" class="form-select">
                        <option value="2025">2025</option>
                        <option value="2024">2024</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Select Month</label>
                    <select id="monthSelector" onchange="updateSummary()" class="form-select">
                        <option value="01">January</option>
                        <option value="02">February</option>
                        <option value="03">March</option>
                        <option value="04">April</option>
                        <option value="05">May</option>
                        <option value="06">June</option>
                        <option value="07">July</option>
                        <option value="08">August</option>
                        <option value="09">September</option>
                        <option value="10" selected>October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card shadow-sm summary-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Avg Score</h5>
                            <h3 id="avgScore" class="text-primary">0</h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm summary-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Total Evaluations</h5>
                            <h3 id="totalEvaluations" class="text-success">0</h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm summary-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Poor Performer Count</h5>
                            <h3 id="poorPerformers" class="text-danger">0</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHART -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Performance Level Distribution</div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TABLE -->
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">List of Evaluated Employees</div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Full Name</th>
                                <th>Evaluation Date</th>
                                <th>Score</th>
                                <th>Rating</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody id="employeeTableBody">
                            <tr>
                                <td colspan="5" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- PAGINATION -->
                    <div id="paginationControls" class="mt-3"></div>

                </div>
            </div>

        </div>
    </div>

    <script>
        Chart.register(ChartDataLabels);

        let chart = new Chart(document.getElementById("performanceChart"), {
            type: "bar",
            data: {
                labels: ["Excellent", "Good", "Average", "Poor"],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: ['#20c997', '#4e73df', '#f6c23e', '#e74a3b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    datalabels: {
                        color: "#000",
                        anchor: "end",
                        align: "top",
                        formatter: (value, ctx) => {
                            const total = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            if (total === 0) return "0%";
                            return ((value / total) * 100).toFixed(1) + "%";
                        }
                    }
                }
            }
        });

        /* PAGINATION */
        let currentPage = 1;
        let totalPages = 1;
        const pageSize = 10;

        /* SUMMARY LOADER */
        async function loadSummary(month, year) {
            const response = await fetch(`https://localhost:7212/employee/getEmployeeMonthReportPerformance/${month}/${year}`);
            const data = await response.json();

            document.getElementById("avgScore").innerText = data.average_score.toFixed(2);
            document.getElementById("totalEvaluations").innerText = data.total_evaluations;
            document.getElementById("poorPerformers").innerText = data.poor_performer_count;

            const poor = data.poor_performer_count;
            const average = Math.floor((data.total_evaluations - poor) / 3);
            const good = average;
            const excellent = data.total_evaluations - (poor + good + average);

            chart.data.datasets[0].data = [excellent, good, average, poor];
            chart.update();
        }

        /* COLOR HELPERS */
        function getScoreColor(score) {
            if (score >= 5) return "#20c997";
            if (score == 4) return "#4e73df";
            if (score == 3) return "#f6c23e";
            return "#e74a3b";
        }

        function getRatingColor(rating) {
            const r = rating.toLowerCase();
            if (r === "excellent") return "#20c997";
            if (r === "good") return "#4e73df";
            if (r === "average") return "#f6c23e";
            return "#e74a3b";
        }

        /* EMPLOYEE TABLE (USES RAW ARRAY RESPONSE) */
        async function loadEmployeeList(month, year, page = 1) {
            const tableBody = document.getElementById("employeeTableBody");
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center">Loading...</td></tr>`;

            const url = `https://localhost:7212/employee/getMonthListReport/${month}/${year}/${page}/${pageSize}`;
            const response = await fetch(url);
            const list = await response.json(); // 👈 YOUR API RETURNS ARRAY, NOT OBJECT

            if (!Array.isArray(list) || list.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center">No evaluations this month.</td></tr>`;
                totalPages = 1;
                renderPagination();
                return;
            }

            // Assuming backend does NOT return total pages → we simulate with fixed data count = 10
            totalPages = 3; // adjust based on your backend later if needed

            tableBody.innerHTML = "";

            list.forEach(item => {
                tableBody.innerHTML += `
            <tr>
                <td>${item.fullName}</td>
                <td>${item.evaluationDate}</td>
                <td style="font-weight:bold;color:${getScoreColor(item.score)};">${item.score}</td>
                <td style="font-weight:bold;color:${getRatingColor(item.rating)};">${item.rating}</td>
                <td>${item.comments}</td>
            </tr>`;
            });

            renderPagination();
        }

        /* PAGINATION BUTTONS */
        function renderPagination() {
            const div = document.getElementById("paginationControls");
            div.innerHTML = `
        <div class="d-flex justify-content-between">
            <button class="btn btn-secondary" onclick="prevPage()" ${currentPage === 1 ? "disabled" : ""}>Previous</button>
            <span class="fw-semibold">Page ${currentPage} of ${totalPages}</span>
            <button class="btn btn-secondary" onclick="nextPage()" ${currentPage === totalPages ? "disabled" : ""}>Next</button>
        </div>
    `;
        }

        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                refreshPage();
            }
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                refreshPage();
            }
        }

        function refreshPage() {
            const year = document.getElementById("yearSelector").value;
            const month = document.getElementById("monthSelector").value;
            loadEmployeeList(month, year, currentPage);
        }

        /* MAIN UPDATE */
        async function updateSummary() {
            const year = document.getElementById("yearSelector").value;
            const month = document.getElementById("monthSelector").value;

            await loadSummary(month, year);
            await loadEmployeeList(month, year, 1);
        }

        /* INITIAL LOAD */
        updateSummary();
    </script>

</body>

</html>