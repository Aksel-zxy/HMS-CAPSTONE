<?
include 'header.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Pharmacy Shortage Forecast</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        body {
            background: linear-gradient(135deg, #f8fafc, #eef2f7);
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
        }

        .card-premium {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .summary-text {
            font-size: 1.05rem;
            line-height: 1.6;
            color: #374151;
        }

        .badge-shortage {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            font-size: 0.8rem;
            padding: 0.45em 0.65em;
            border-radius: 0.6rem;
            color: white;
        }

        .table thead th {
            background: #dc3545;
            color: white;
            border: none;
        }

        .table tbody tr:hover {
            background-color: #fef2f2;
        }

        .header-icon {
            font-size: 2rem;
            color: #dc3545;
        }

        .insight-card {
            border-left: 6px solid #dc3545;
            background: linear-gradient(135deg, #fff, #fef2f2);
        }

        .insight-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #b02a37;
        }

        .insight-label {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .severity-high {
            color: #b02a37;
            font-weight: 600;
        }

        .severity-medium {
            color: #fd7e14;
            font-weight: 600;
        }

        .severity-low {
            color: #198754;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <?
        include 'sidebar.php'
        ?>
        <div class="container py-5" id="dashboardContainer">

            <!-- HEADER -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div class="text-center w-100">
                    <h2 class="fw-bold">
                        <i class="bi bi-exclamation-triangle-fill me-2 header-icon"></i>
                        Predicted Medicine Shortages
                    </h2>
                    <p class="text-muted mb-0">
                        AI-driven monthly forecast — shortage-only view
                    </p>
                </div>

                <button class="btn btn-danger ms-3" id="downloadBtn">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                </button>
            </div>

            <!-- SUMMARY -->
            <div id="summaryCard" class="card card-premium p-4 mb-4">
                <p id="summaryText" class="summary-text mb-0">
                    Loading pharmacy shortage forecast...
                </p>
            </div>

            <!-- KEY INSIGHTS -->
            <div id="insightsRow" class="row g-3 mb-4 d-none">
                <div class="col-md-3">
                    <div class="card card-premium p-3 insight-card">
                        <div class="insight-label">Most Dispensed Shortage Med</div>
                        <div id="insightTopMed" class="insight-value">—</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card card-premium p-3 insight-card">
                        <div class="insight-label">Total Units Dispensed (Shortage)</div>
                        <div id="insightTotal" class="insight-value">0</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card card-premium p-3 insight-card">
                        <div class="insight-label">Average Units Dispensed</div>
                        <div id="insightAverage" class="insight-value">0</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card card-premium p-3 insight-card">
                        <div class="insight-label">Zero-Dispense Shortages</div>
                        <div id="insightZero" class="insight-value">0</div>
                    </div>
                </div>
            </div>

            <!-- SUGGESTIONS -->
            <div id="suggestionsCard" class="card card-premium p-4 mb-4 d-none">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-lightbulb-fill me-2 text-warning"></i>
                    Operational Suggestions
                </h5>
                <ul id="suggestionsList" class="mb-0"></ul>
            </div>

            <!-- CHART -->
            <div id="chartCard" class="card card-premium p-4 mb-4 d-none">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-bar-chart-line me-2"></i>
                    Shortage Medicines Overview (Previous Month Dispensed)
                </h5>
                <canvas id="shortageChart" height="120"></canvas>
            </div>

            <!-- TABLE -->
            <div id="tableCard" class="card card-premium p-4 d-none">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-table me-2"></i>
                    Top 8 Medicines at Risk of Shortage
                </h5>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Category</th>
                                <th>Dosage</th>
                                <th>Avg Daily Use</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="forecastTable"></tbody>
                    </table>
                </div>
            </div>

        </div>

        <script>
            const forecastApiUrl = "https://localhost:7212/journal/getMonthMedicineShortageResult";
            const prevMonthApiUrl = "https://localhost:7212/journal/getPreviousMonthMedicineDispensed";

            const medicineMap = {
                21: {
                    name: "Biogesic",
                    category: "Paracetamol",
                    dosage: "500 MG"
                },
                22: {
                    name: "Tempra",
                    category: "Paracetamol",
                    dosage: "120 MG"
                },
                23: {
                    name: "Calpol",
                    category: "Paracetamol",
                    dosage: "250 MG"
                },
                24: {
                    name: "Panadol",
                    category: "Paracetamol",
                    dosage: "500 MG"
                },
                25: {
                    name: "Acephen",
                    category: "Paracetamol",
                    dosage: "650 MG"
                },
                26: {
                    name: "Ibuprofen",
                    category: "Pain Killers",
                    dosage: "400 MG"
                },
                27: {
                    name: "Mefenamic Acid (Ponstan)",
                    category: "Pain Killers",
                    dosage: "500 MG"
                },
                28: {
                    name: "Tramadol",
                    category: "Pain Killers",
                    dosage: "100 MG"
                },
                29: {
                    name: "Diclofenac",
                    category: "Pain Killers",
                    dosage: "50 MG"
                },
                30: {
                    name: "Naproxen",
                    category: "Pain Killers",
                    dosage: "500 MG"
                },
                31: {
                    name: "Amoxicillin",
                    category: "Antibiotics",
                    dosage: "500 MG"
                }
            };

            let chartInstance = null;

            async function loadForecast() {
                try {
                    const [forecastRes, prevMonthRes] = await Promise.all([
                        fetch(forecastApiUrl),
                        fetch(prevMonthApiUrl)
                    ]);

                    const forecastData = await forecastRes.json();
                    const prevMonthData = await prevMonthRes.json();

                    if (!forecastData.length) return;

                    // Map previous month dispensed by med_id
                    const prevMonthMap = {};
                    prevMonthData.forEach(x => prevMonthMap[x.med_id] = x.dispensed);

                    // Take top 8 shortage medicines
                    const top8Forecast = forecastData.slice(0, 8);
                    top8Forecast.forEach(item => item.prevDispensed = prevMonthMap[item.med_id] ?? 0);

                    const monthName = new Date(top8Forecast[0].year, top8Forecast[0].month - 1)
                        .toLocaleString("default", {
                            month: "long"
                        });

                    document.getElementById("summaryText").innerText =
                        `For ${monthName} ${top8Forecast[0].year}, 8 medicines are predicted to experience shortages.`;

                    const tableBody = document.getElementById("forecastTable");
                    tableBody.innerHTML = "";

                    const chartLabels = [];
                    const chartValues = [];
                    let totalDispensed = 0,
                        zeroCount = 0;

                    top8Forecast.forEach(item => {
                        const med = medicineMap[item.med_id];
                        chartLabels.push(med.name);
                        chartValues.push(item.prevDispensed);

                        totalDispensed += item.prevDispensed;
                        if (item.prevDispensed === 0) zeroCount++;

                        tableBody.insertAdjacentHTML("beforeend", `
                        <tr>
                            <td class="fw-semibold">${med.name}</td>
                            <td>${med.category}</td>
                            <td>${med.dosage}</td>
                            <td>${item.avg_daily_use}</td>
                            <td><span class="badge badge-shortage">Shortage</span></td>
                        </tr>
                    `);
                    });

                    // Insights
                    const topMed = top8Forecast.reduce((prev, curr) => curr.prevDispensed > prev.prevDispensed ? curr : prev, top8Forecast[0]);
                    const avgDispensed = (totalDispensed / top8Forecast.length).toFixed(1);

                    document.getElementById("insightTopMed").innerText = medicineMap[topMed.med_id].name;
                    document.getElementById("insightTotal").innerText = totalDispensed;
                    document.getElementById("insightAverage").innerText = avgDispensed;
                    document.getElementById("insightZero").innerText = zeroCount;

                    document.getElementById("insightsRow").classList.remove("d-none");

                    // Suggestions
                    const suggestions = [];

                    function addSuggestion(text, severity) {
                        const cls = severity === "High" ? "severity-high" : severity === "Medium" ? "severity-medium" : "severity-low";
                        suggestions.push(`<li><span class="${cls}">${severity}:</span> ${text}</li>`);
                    }
                    if (zeroCount > 3) addSuggestion("Several medicines had zero usage last month. Validate demand forecasts.", "High");
                    if (avgDispensed > 100) addSuggestion("High average usage. Increase reorder levels for high-risk medicines.", "High");
                    if (top8Forecast.length > 6) addSuggestion("Multiple medicines at risk. Diversify suppliers.", "Medium");
                    addSuggestion("Review emergency stock buffers for critical drugs.", "Low");
                    document.getElementById("suggestionsList").innerHTML = suggestions.join("");
                    document.getElementById("suggestionsCard").classList.remove("d-none");

                    // Chart
                    const ctx = document.getElementById("shortageChart").getContext("2d");
                    if (chartInstance) chartInstance.destroy();
                    chartInstance = new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: "Previous Month Dispensed",
                                data: chartValues,
                                backgroundColor: 'rgba(220,53,69,0.7)'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: true
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: "Units Dispensed"
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: "Medicines"
                                    }
                                }
                            }
                        }
                    });

                    document.getElementById("chartCard").classList.remove("d-none");
                    document.getElementById("tableCard").classList.remove("d-none");

                } catch (err) {
                    console.error(err);
                }
            }

            document.getElementById("downloadBtn").addEventListener("click", async () => {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF("p", "mm", "a4");
                const container = document.getElementById("dashboardContainer");
                const chartCanvas = document.getElementById("shortageChart");
                const chartImg = chartCanvas.toDataURL("image/png");

                await doc.html(container, {
                    x: 10,
                    y: 10,
                    width: 190,
                    callback: function(doc) {
                        doc.addPage();
                        doc.setFontSize(14);
                        doc.text("Shortage Chart", 14, 20);
                        doc.addImage(chartImg, "PNG", 10, 25, 180, 90);
                        doc.save(`Pharmacy_Shortage_Forecast_${new Date().toISOString().split("T")[0]}.pdf`);
                    }
                });
            });

            loadForecast();
        </script>
    </div>
</body>

</html>