<?php
include 'header.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Patient Admission Forecast Dashboard</title>

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
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .07);
            transition: transform 0.2s ease;
        }

        .card-premium:hover {
            transform: translateY(-4px);
        }

        .stat-icon {
            font-size: 34px;
            opacity: .85;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
        }

        .chart-container {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .05);
        }

        .summary-text {
            font-size: 16px;
            color: #495057;
            line-height: 1.7;
        }

        .suggestion-card {
            border-left: 5px solid;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }

        .suggestion-high {
            border-color: #dc3545;
        }

        .suggestion-medium {
            border-color: #ffc107;
        }

        .suggestion-low {
            border-color: #198754;
        }

        .severity-badge {
            font-size: 0.8rem;
            margin-right: 8px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php
        include 'sidebar.php'
        ?>
        <div class="container py-5">
            <!-- HEADER -->
            <div class="mb-4 text-center">
                <h2 class="fw-bold">
                    <i class="bi bi-people-fill me-2"></i>
                    Patient Admission Forecast
                </h2>
                <p class="text-muted mb-0">
                    Historical admissions trend and AI-driven forecast
                </p>
            </div>

            <!-- SUMMARY TEXT -->
            <div class="card card-premium p-4 mb-4">
                <p id="summaryText" class="summary-text mb-0">
                    Loading patient admission forecast...
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
                        <i class="bi bi-hospital stat-icon text-danger mb-2"></i>
                        <div class="stat-value" id="forecastAdmission">—</div>
                        <div class="stat-label">Forecasted Admissions</div>
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
                    Patient Admission Trend (Historical + Forecast)
                </h6>
                <canvas id="admissionChart" height="120"></canvas>
            </div>

            <!-- INSIGHTS -->
            <div class="card card-premium p-4 mb-4">
                <h5 class="fw-bold mb-3">Key Insights</h5>
                <ul id="insightsList" class="list-unstyled mb-0">
                    <li>Loading insights...</li>
                </ul>
            </div>

            <!-- SUGGESTIONS -->
            <div class="card card-premium p-4 mb-4">
                <h5 class="fw-bold">
                    <i class="bi bi-lightbulb me-2 text-warning"></i>
                    Operational Suggestions
                </h5>
                <ul id="suggestionsList" class="mb-0"></ul>
            </div>

            <!-- PDF DOWNLOAD BUTTON -->
            <div class="text-end mb-5">
                <button class="btn btn-danger" id="downloadPdfBtn">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                </button>
            </div>

        </div>

        <script>
            let chart;

            const forecastUrl = "https://localhost:7212/patient/getPatientAdmissionForecast";
            const historyUrl = "https://localhost:7212/patient/getPreviousMonthsAdmissionReport";

            async function loadPatientAdmissionForecast() {
                try {
                    const [forecastRes, historyRes] = await Promise.all([
                        fetch(forecastUrl),
                        fetch(historyUrl)
                    ]);

                    if (!forecastRes.ok || !historyRes.ok) throw new Error("Failed to fetch admission data.");

                    const forecast = await forecastRes.json();
                    const history = await historyRes.json();

                    const monthName = new Date(forecast.year, forecast.month - 1)
                        .toLocaleString("default", {
                            month: "long"
                        });

                    // Summary cards
                    document.getElementById("forecastMonth").innerText = `${monthName} ${forecast.year}`;
                    document.getElementById("forecastAdmission").innerText = forecast.total_admission.toLocaleString();

                    const lastActual = history[history.length - 1]?.total_admission || 0;
                    const delta = forecast.total_admission - lastActual;
                    const deltaPercent = lastActual ? ((delta / lastActual) * 100).toFixed(2) : 0;

                    const deltaEl = document.getElementById("forecastDelta");
                    deltaEl.innerText = `${delta >= 0 ? "+" : ""}${delta.toLocaleString()} (${deltaPercent}%)`;
                    deltaEl.classList.remove("text-success", "text-danger");
                    deltaEl.classList.add(delta >= 0 ? "text-success" : "text-danger");

                    // Summary text
                    document.getElementById("summaryText").innerHTML =
                        `For <strong>${monthName} ${forecast.year}</strong>, patient admissions are projected to reach 
                    <strong>${forecast.total_admission.toLocaleString()}</strong>. 
                    This represents a 
                    <strong>${delta >= 0 ? "increase" : "decrease"}</strong> of 
                    <strong>${Math.abs(delta).toLocaleString()}</strong> 
                    (${Math.abs(deltaPercent)}%) compared to the previous month. 
                    ${delta >= 0
                        ? "Higher admissions may require staffing and bed capacity planning."
                        : "Lower admissions may ease operational load but affect revenue planning."}`;

                    // Trend chart
                    const labels = history.map(h => new Date(h.year, h.month - 1).toLocaleString("default", {
                        month: "short"
                    }));
                    const values = history.map(h => h.total_admission);
                    labels.push(`${monthName} (Forecast)`);
                    values.push(forecast.total_admission);

                    const ctx = document.getElementById("admissionChart");
                    if (chart) chart.destroy();
                    chart = new Chart(ctx, {
                        type: "line",
                        data: {
                            labels,
                            datasets: [{
                                label: "Patient Admissions",
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
                                        label: ctx => ctx.raw.toLocaleString() + " admissions"
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        callback: value => value.toLocaleString()
                                    }
                                }
                            }
                        }
                    });

                    // Insights
                    const insights = [];
                    insights.push(delta >= 0 ? `Projected admission growth of ${deltaPercent}% compared to last month.` :
                        `Projected admission reduction of ${Math.abs(deltaPercent)}% compared to last month.`);
                    insights.push(forecast.total_admission > lastActual ? "Admissions are trending upward, indicating increased patient inflow." :
                        "Admissions are trending downward, indicating reduced patient inflow.");
                    insights.push(forecast.total_admission > 180 ? "High admission volume may require capacity and staffing review." :
                        "Admission volume remains within manageable operational limits.");
                    document.getElementById("insightsList").innerHTML =
                        insights.map(i => `<li>• ${i}</li>`).join("");

                    // ===============================
                    // Suggestions with Severity Cards
                    // ===============================
                    const suggestionsList = document.getElementById("suggestionsList");
                    suggestionsList.innerHTML = "";

                    function addSuggestionCard(text, severity) {
                        let className = "";
                        if (severity === "High") className = "suggestion-card suggestion-high";
                        else if (severity === "Medium") className = "suggestion-card suggestion-medium";
                        else className = "suggestion-card suggestion-low";

                        const li = document.createElement("li");
                        li.innerHTML = `<div class="${className}"><span class="badge bg-${severity === "High" ? "danger" : severity === "Medium" ? "warning" : "success"} severity-badge">${severity}</span>${text}</div>`;
                        suggestionsList.appendChild(li);
                    }

                    if (deltaPercent >= 8) addSuggestionCard("Admissions rising sharply. Increase staffing and bed capacity.", "High");
                    else if (deltaPercent >= 3) addSuggestionCard("Moderate growth detected. Review shift allocations and overtime.", "Medium");
                    else if (deltaPercent <= -5) addSuggestionCard("Admissions projected to decline. Evaluate cost optimization.", "High");
                    else if (deltaPercent < 0) addSuggestionCard("Slight decrease expected. Monitor resource utilization.", "Low");

                    if (forecast.total_admission > 170) addSuggestionCard("High admissions may strain beds. Prepare overflow protocols.", "High");
                    else if (forecast.total_admission < 130) addSuggestionCard("Lower admissions may free capacity. Schedule elective procedures or maintenance.", "Medium");

                    const firstMonth = history[0]?.total_admission || 0;
                    const lastMonth = history[history.length - 1]?.total_admission || 0;
                    const longTermGrowthRate = firstMonth ? ((lastMonth - firstMonth) / firstMonth) * 100 : 0;
                    if (longTermGrowthRate > 20) addSuggestionCard("Sustained long-term admission growth suggests need for expansion.", "High");
                    else if (longTermGrowthRate < -10) addSuggestionCard("Long-term decline in admissions may require service diversification or marketing.", "Medium");

                    let volatility = 0;
                    for (let i = 1; i < history.length; i++) volatility += Math.abs(history[i].total_admission - history[i - 1].total_admission);
                    volatility /= history.length;
                    if (volatility > 12) addSuggestionCard("Admission volume is volatile. Improve forecasting buffers and flexible staffing.", "Medium");

                    if (suggestionsList.children.length === 0)
                        addSuggestionCard("Admission trends are stable. Maintain current operational planning.", "Low");

                } catch (err) {
                    console.error(err);
                    alert("Failed to fetch patient admission data. Make sure the API endpoints are running.");
                }
            }

            window.onload = loadPatientAdmissionForecast;

            // ===============================
            // PDF EXPORT
            // ===============================
            document.getElementById("downloadPdfBtn").addEventListener("click", async () => {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF("p", "mm", "a4");
                const container = document.querySelector(".container");

                await doc.html(container, {
                    x: 10,
                    y: 10,
                    width: 190,
                    callback: function(doc) {
                        doc.save(`Patient_Admission_Forecast_${new Date().toISOString().split("T")[0]}.pdf`);
                    }
                });
            });
        </script>
    </div>
</body>

</html>