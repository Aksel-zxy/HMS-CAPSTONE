<?php include 'header.php' ?>
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

        .insight-box {
            border-left: 4px solid #0d6efd;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
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
                        <option value="2026">2026</option>
                        <option value="2025">2025</option>
                        <option value="2024">2024</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Select Month</label>
                    <select id="monthSelector" onchange="updateSummary()" class="form-select">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
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

            <!-- INSIGHTS -->
            <h4 class="fw-semibold mb-2">Key Insights</h4>
            <div id="insightBox" class="insight-box">
                Loading insights...
            </div>

        </div>
    </div>

    <script>
        Chart.register(ChartDataLabels);

        // Setup Chart
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

        /* LOAD SUMMARY USING NEW API FORMAT */
        async function loadSummary(month, year) {
            const response = await fetch(`https://localhost:7212/employee/getEmployeeMonthReportPerformance?month=${month}&year=${year}`);
            const data = await response.json();

            document.getElementById("avgScore").innerText = data.average_score;
            document.getElementById("totalEvaluations").innerText = data.total_evaluations;
            document.getElementById("poorPerformers").innerText = data.poor_performer_count;

            /* Chart Distribution Estimate */
            const total = data.total_evaluations;
            const poor = data.poor_performer_count;

            // Simple logic to distribute remaining:
            const average = Math.max(0, Math.floor((total - poor) / 3));
            const good = average;
            const excellent = Math.max(0, total - (poor + good + average));

            chart.data.datasets[0].data = [excellent, good, average, poor];
            chart.update();

            generateInsights(data, excellent, good, average, poor);
        }

        /* INSIGHT GENERATION */
        function generateInsights(data, excellent, good, average, poor) {
            let insights = [];

            // Insight #1 - Performance Strength
            if (excellent > good && excellent > average && excellent > poor) {
                insights.push("A large portion of employees achieved **Excellent** ratings, indicating strong overall performance.");
            } else if (poor > excellent) {
                insights.push("There is a noticeable concentration of **Poor performers**, signaling a need for training or intervention.");
            }

            // Insight #2 - Average Score Health
            if (data.average_score >= 85) {
                insights.push("The average score indicates **high-performing employees** this month.");
            } else if (data.average_score >= 60) {
                insights.push("Employee performance is **moderate**, with opportunities for improvement.");
            } else {
                insights.push("Performance level is **below expectations** — action may be required.");
            }

            // Insight #3 - Evaluation count
            if (data.total_evaluations <= 3) {
                insights.push("Only a few evaluations were submitted — results may not represent the entire workforce.");
            } else if (data.total_evaluations >= 10) {
                insights.push("High evaluation count provides a **reliable performance overview**.");
            }

            // Insight #4 - Poor performer ratio
            const poorPct = (data.poor_performer_count / data.total_evaluations) * 100;
            if (poorPct >= 30) {
                insights.push("Over **30%** of employees scored poorly — check departmental issues or workload problems.");
            }

            document.getElementById("insightBox").innerHTML =
                insights.map(i => `<p>• ${i}</p>`).join("");
        }

        /* MAIN UPDATE */
        async function updateSummary() {
            const year = document.getElementById("yearSelector").value;
            const month = document.getElementById("monthSelector").value;
            await loadSummary(month, year);
        }

        /* INITIAL LOAD */
        updateSummary();
    </script>

</body>

</html>