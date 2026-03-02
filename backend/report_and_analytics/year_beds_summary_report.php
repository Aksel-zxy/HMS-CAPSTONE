<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Beds & Discharge Analytics Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jsPDF / AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <!-- Excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        body {
            background: #f4f6f9;
        }

        /* Floating AI Button */
        #aiButton {
            position: fixed;
            bottom: 28px;
            right: 28px;
            background: #0d6efd;
            color: white;
            padding: 15px 18px;
            border-radius: 50%;
            font-size: 22px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.22);
            z-index: 9999;
            transition: 0.25s;
        }

        #aiButton:hover {
            transform: scale(1.1);
        }

        /* Sliding AI Drawer */
        #aiDrawer {
            position: fixed;
            top: 0;
            right: -380px;
            width: 360px;
            height: 100vh;
            background: #ffffff;
            box-shadow: -5px 0 12px rgba(0, 0, 0, 0.15);
            padding: 22px;
            z-index: 9998;
            border-left: 5px solid #0d6efd;
            overflow-y: auto;
            transition: right 0.35s ease;
        }

        #aiDrawer.open {
            right: 0;
        }

        #aiDrawer h4 {
            font-weight: bold;
            color: #0d6efd;
        }

        #closeDrawer {
            position: absolute;
            top: 10px;
            right: 18px;
            font-size: 20px;
            cursor: pointer;
        }

        /* Mobile Fix */
        @media(max-width: 992px) {
            #aiDrawer {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="d-flex">

        <?php include 'sidebar.php'; ?>

        <div class="container py-4">

            <h2 class="mb-4 fw-bold text-primary">🛏️ Beds & Discharge Analytics</h2>

            <!-- FILTER -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Select Month Range</h5>

                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">From Month</label>
                            <select class="form-select" id="startMonth" required>
                                <option value="">Select</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>"><?= date("F", mktime(0, 0, 0, $i, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">From Year</label>
                            <select class="form-select" id="startYear" required></select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">To Month</label>
                            <select class="form-select" id="endMonth" required>
                                <option value="">Select</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>"><?= date("F", mktime(0, 0, 0, $i, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">To Year</label>
                            <select class="form-select" id="endYear" required></select>
                        </div>

                        <div class="col-md-12 mt-3">
                            <button class="btn btn-primary w-100">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SUMMARY -->
            <div class="card shadow-sm mb-4 d-none" id="summaryCard">
                <div class="card-body">

                    <h5 class="fw-semibold mb-3">Summary Overview</h5>

                    <div class="row text-center">
                        <div class="col-md-3">
                            <h4 class="text-primary" id="sumTotalBeds">0</h4>
                            <p>Total Beds</p>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-danger" id="sumOccupiedBeds">0%</h4>
                            <p>Avg Occupied</p>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-success" id="sumAvailableBeds">0%</h4>
                            <p>Avg Available</p>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-warning" id="sumBrokenBeds">0%</h4>
                            <p>Avg Broken</p>
                        </div>
                    </div>

                    <div class="row text-center mt-3">
                        <div class="col-md-12">
                            <h4 class="text-info" id="sumDischarged">0</h4>
                            <p>Total Discharged</p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- EXPORT -->
            <div class="d-none mb-4 text-end" id="exportButtons">
                <button class="btn btn-success me-2" onclick="exportExcel()">Export Excel</button>
                <button class="btn btn-danger" onclick="exportPDF()">Export PDF</button>
            </div>

            <!-- TABLE -->
            <div class="card shadow-sm mb-4 d-none" id="tableCard">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Monthly Breakdown</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered text-center" id="reportTable">
                            <thead class="table-primary">
                                <tr>
                                    <th>Month</th>
                                    <th>Total Beds</th>
                                    <th>Occupied</th>
                                    <th>Available</th>
                                    <th>Discharged</th>
                                    <th>Broken</th>
                                </tr>
                            </thead>
                            <tbody id="monthRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CHARTS -->
            <div class="card shadow-sm mb-4 d-none" id="chartCard">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Charts</h5>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <canvas id="bedsChart" height="150"></canvas>
                        </div>

                        <div class="col-md-6">
                            <canvas id="barChart" height="150"></canvas>
                        </div>

                        <div class="col-md-6">
                            <canvas id="pieChart" height="150"></canvas>
                        </div>

                        <div class="col-md-6">
                            <canvas id="dischargeChart" height="150"></canvas>
                        </div>
                    </div>

                </div>
            </div>

            <!-- AI INSIGHTS DRAWER (SLIDING) -->
            <div id="aiDrawer">
                <span id="closeDrawer">&times;</span>
                <h4>AI Insights</h4>
                <div id="aiInsights"></div>

                <hr>

                <h4>Recommendations</h4>
                <div id="aiReco"></div>

                <hr>

                <h4>Forecast</h4>
                <div id="aiForecast"></div>
            </div>

            <!-- Floating AI Button -->
            <div id="aiButton">
                🤖
            </div>

        </div>
    </div>


    <script>
        /* ===================== Populate Years ===================== */
        function populateYears(id) {
            const sel = document.getElementById(id);
            const now = new Date().getFullYear();
            for (let y = 2010; y <= now + 2; y++) {
                sel.innerHTML += `<option value="${y}">${y}</option>`;
            }
        }
        populateYears("startYear");
        populateYears("endYear");


        /* ===================== Chart Instances ===================== */
        let bedsChart, barChart, pieChart, dischargeChart;

        /* ===================== AI Drawer Logic ===================== */
        document.getElementById("aiButton").onclick = () => {
            document.getElementById("aiDrawer").classList.add("open");
        };
        document.getElementById("closeDrawer").onclick = () => {
            document.getElementById("aiDrawer").classList.remove("open");
        };

        /* ===================== Linear Forecast ===================== */
        function linearForecast(values, next = 3) {
            const n = values.length;
            const x = values.map((_, i) => i + 1);
            const y = values;

            const sumX = x.reduce((a, b) => a + b, 0);
            const sumY = y.reduce((a, b) => a + b, 0);
            const sumXY = x.reduce((a, b, i) => a + (b * y[i]), 0);
            const sumX2 = x.reduce((a, b) => a + (b * b), 0);

            const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
            const intercept = (sumY - slope * sumX) / n;

            let forecast = [];
            for (let i = 1; i <= next; i++) {
                const xN = n + i;
                forecast.push(Math.round(intercept + slope * xN));
            }
            return forecast;
        }

        /* ===================== Load Report ===================== */
        document.getElementById("filterForm").addEventListener("submit", async (e) => {
            e.preventDefault();

            const url = `https://bsis-03.keikaizen.xyz/property/monthBedsAndDischargedRangeQuery?start=${startMonth.value}&startYear=${startYear.value}&endMonth=${endMonth.value}&endYear=${endYear.value}`;
            const res = await fetch(url);
            const data = await res.json();

            summaryCard.classList.remove("d-none");
            tableCard.classList.remove("d-none");
            chartCard.classList.remove("d-none");
            exportButtons.classList.remove("d-none");

            const names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

            /* --- Summary --- */
            let sumBeds = 0,
                sumOcc = 0,
                sumAvail = 0,
                sumBroken = 0;
            data.months.forEach(m => {
                sumBeds += m.total_beds;
                sumOcc += m.occupied_beds;
                sumAvail += m.available_beds;
                sumBroken += m.broken_beds;
            });
            const c = data.months.length;

            sumTotalBeds.innerText = sumBeds;
            sumOccupiedBeds.innerText = ((sumOcc / sumBeds) * 100).toFixed(1) + "%";
            sumAvailableBeds.innerText = ((sumAvail / sumBeds) * 100).toFixed(1) + "%";
            sumBrokenBeds.innerText = ((sumBroken / sumBeds) * 100).toFixed(1) + "%";
            sumDischarged.innerText = data.recently_discharged;

            /* --- Table --- */
            monthRows.innerHTML = data.months.map(m => `
        <tr>
            <td>${names[m.month-1]} ${m.year}</td>
            <td>${m.total_beds}</td>
            <td class="text-danger fw-bold">${m.occupied_beds}</td>
            <td class="text-success fw-bold">${m.available_beds}</td>
            <td class="text-info fw-bold">${m.recently_discharged}</td>
            <td class="text-warning fw-bold">${m.broken_beds}</td>
        </tr>
    `).join("");

            /* --- Chart Values --- */
            const labels = data.months.map(m => names[m.month - 1]);
            const occ = data.months.map(m => m.occupied_beds);
            const avail = data.months.map(m => m.available_beds);
            const disc = data.months.map(m => m.recently_discharged);

            /* --- Forecasting --- */
            const forecastOcc = linearForecast(occ);
            const forecastAvail = linearForecast(avail);
            const forecastDisc = linearForecast(disc);

            /* --- Charts --- */
            if (bedsChart) bedsChart.destroy();
            bedsChart = new Chart(document.getElementById("bedsChart"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                            label: "Occupied",
                            data: occ,
                            borderColor: "red",
                            borderWidth: 3
                        },
                        {
                            label: "Available",
                            data: avail,
                            borderColor: "green",
                            borderWidth: 3
                        }
                    ]
                }
            });

            if (barChart) barChart.destroy();
            barChart = new Chart(document.getElementById("barChart"), {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                            label: "Occupied",
                            data: occ,
                            backgroundColor: "rgba(255,0,0,0.7)"
                        },
                        {
                            label: "Available",
                            data: avail,
                            backgroundColor: "rgba(0,128,0,0.7)"
                        }
                    ]
                }
            });

            if (pieChart) pieChart.destroy();
            pieChart = new Chart(document.getElementById("pieChart"), {
                type: "pie",
                data: {
                    labels: ["Avg Occ", "Avg Avail", "Avg Broken"],
                    datasets: [{
                        data: [sumOcc / c, sumAvail / c, sumBroken / c]
                    }]
                }
            });

            if (dischargeChart) dischargeChart.destroy();
            dischargeChart = new Chart(document.getElementById("dischargeChart"), {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        label: "Discharged",
                        data: disc,
                        borderColor: "blue",
                        borderWidth: 3
                    }]
                }
            });

            /* --- AI Insights Drawer Output --- */
            aiInsights.innerHTML = `
        • Highest occupancy month: <b>${labels[occ.indexOf(Math.max(...occ))]}</b><br>
        • Lowest availability month: <b>${labels[avail.indexOf(Math.min(...avail))]}</b><br>
        • Total discharged: <b>${data.recently_discharged}</b><br>
        • Avg bed occupancy: <b>${((sumOcc/sumBeds)*100).toFixed(1)}%</b>
    `;

            aiReco.innerHTML = `
        • Increase staffing during peak occupancy months.<br>
        • Repair broken beds early to reduce shortages.<br>
        • Improve discharge processing speed during high-load months.<br>
        • Balance bed distribution across departments.
    `;

            aiForecast.innerHTML = `
        <b>Occupied Beds Prediction:</b> ${forecastOcc.join(", ")}<br>
        <b>Available Beds Prediction:</b> ${forecastAvail.join(", ")}<br>
        <b>Discharge Prediction:</b> ${forecastDisc.join(", ")}
    `;
        });

        /* ===================== Export Excel ===================== */
        function exportExcel() {
            const wb = XLSX.utils.table_to_book(reportTable);
            XLSX.writeFile(wb, "Beds_Report.xlsx");
        }

        /* ===================== Export PDF ===================== */
        function exportPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            doc.text("Beds Report", 14, 15);
            doc.autoTable({
                html: "#reportTable",
                startY: 20
            });
            doc.save("Beds_Report.pdf");
        }
    </script>

</body>

</html>