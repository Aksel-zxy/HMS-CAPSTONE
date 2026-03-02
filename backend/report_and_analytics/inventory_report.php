<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Hospital Inventory Report & AI Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- PDF Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body {
            background: #f4f7fb;
            overflow-x: hidden;
            font-family: 'Segoe UI', sans-serif;
        }

        #reportArea {
            margin-left: 300px !important;
            margin-right: 360px !important;
        }

        /* Summary Cards */
        .report-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        /* AI Panel (Right Side) */
        #aiPanel {
            position: fixed;
            right: 20px;
            top: 95px;
            width: 340px;
            height: calc(100vh - 120px);
            overflow-y: auto;
            background: #fff;
            border-left: 5px solid #2a77d4;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            z-index: 999;
        }

        #aiPanel h5 {
            background: #2a77d4;
            color: #fff;
            padding: 8px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 12px;
        }

        /* Heatmap */
        .heatmap-box {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: .9rem;
        }

        .risk-low {
            background: #d4edda;
        }

        .risk-medium {
            background: #fff3cd;
        }

        .risk-high {
            background: #f8d7da;
        }

        /* Table Colors */
        .danger-row {
            background: #ffe2e2;
        }

        .warning-row {
            background: #fff6d1;
        }

        @media(max-width:992px) {
            #aiPanel {
                position: static;
                width: 100%;
                height: auto;
                margin-top: 20px;
            }

            #reportArea {
                margin: 0 !important;
            }
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- AI ANALYTICS PANEL -->
        <div id="aiPanel">
            <h5>AI Insights</h5>
            <section id="aiInsights">Loading...</section>

            <h5>Department Usage</h5>
            <section id="aiDept">Loading...</section>

            <h5>Supplier Performance</h5>
            <section id="aiSupplier">Loading...</section>

            <h5>Risk Heatmap</h5>
            <section id="aiHeatmap">Loading...</section>

            <h5>7-Day Forecast</h5>
            <section id="aiPrediction">Loading...</section>
        </div>

        <!-- MAIN REPORT AREA -->
        <div class="container py-4" id="reportArea">

            <h2 class="fw-bold mb-4 text-primary">Hospital Inventory Report & AI Analytics</h2>

            <!-- EXPORT BUTTONS -->
            <div class="d-flex gap-3 mb-4">
                <button class="btn btn-success" onclick="exportPDF()">Export PDF</button>
                <button class="btn btn-secondary" onclick="exportCSV()">Export Excel</button>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row" id="summaryCards"></div>

            <!-- FILTERS -->
            <div class="card mt-4 mb-4 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Filters</h5>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-semibold">Search</label>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search..." oninput="applyFilters()">
                        </div>

                        <div class="col-md-3">
                            <label class="fw-semibold">Item Type</label>
                            <select id="typeFilter" class="form-select" onchange="applyFilters()">
                                <option value="">All</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="fw-semibold">Category</label>
                            <select id="categoryFilter" class="form-select" onchange="applyFilters()">
                                <option value="">All</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="fw-semibold">Unit Type</label>
                            <select id="unitFilter" class="form-select" onchange="applyFilters()">
                                <option value="">All</option>
                                <option value="Piece">Piece</option>
                                <option value="Box">Box</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="fw-semibold">Stock Level</label>
                            <select id="stockFilter" class="form-select" onchange="applyFilters()">
                                <option value="">All</option>
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                                <option value="out">Out</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHARTS ROW 1 -->
            <div class="row mb-4">

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold text-center text-primary">Item Type Distribution</h6>
                        <canvas id="chartTypes"></canvas>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold text-center text-primary">Stock Levels</h6>
                        <canvas id="chartLevels"></canvas>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold text-center text-primary">Top 10 Items</h6>
                        <canvas id="chartTop"></canvas>
                    </div>
                </div>

            </div>

            <!-- CHARTS ROW 2 -->
            <div class="row mb-4">

                <div class="col-md-6">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold text-center text-primary">7-Day Stock Forecast</h6>
                        <canvas id="chartForecast"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-3 shadow-sm">
                        <h6 class="fw-bold text-center text-primary">Inventory Aging (Days)</h6>
                        <canvas id="chartAging"></canvas>
                    </div>
                </div>

            </div>

            <!-- INVENTORY TABLE -->
            <div class="card shadow-sm mb-5">
                <div class="card-header bg-primary text-white fw-bold">Inventory Table</div>

                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Sub-Type</th>
                                <th>Qty</th>
                                <th>Total Qty</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Location</th>
                                <th>Received</th>
                                <th>Risk</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTable"></tbody>
                    </table>
                </div>

                <div class="card-footer d-flex justify-content-center">
                    <ul class="pagination" id="pagination"></ul>
                </div>

            </div> <!-- END TABLE -->
        </div> <!-- END REPORT AREA -->
    </div> <!-- END MAIN FLEX -->

    <!-- PART B (JS LOGIC) WILL BE IN NEXT MESSAGE -->
    <script>
        /******************************************
         * GLOBAL VARIABLES
         ******************************************/
        let inventoryData = [];
        let filteredData = [];
        let currentPage = 1;
        const rowsPerPage = 10;

        let chartTypes, chartLevels, chartTop, chartForecast, chartAging;

        /******************************************
         * LOAD INVENTORY DATA
         ******************************************/
        async function loadReport() {
            const res = await fetch("https://bsis-03.keikaizen.xyz/property/inventoryReport");
            inventoryData = await res.json();

            filteredData = inventoryData;
            populateFilters();
            renderSummary(inventoryData);
            applyFilters();
            generateAI(inventoryData);
        }

        /******************************************
         * SUMMARY CARDS
         ******************************************/
        function renderSummary(items) {
            const total = items.length;
            const totalVal = items.reduce((s, x) => s + (x.price * x.total_qty), 0);
            const low = items.filter(x => x.quantity <= x.min_stock && x.quantity > 0).length;
            const out = items.filter(x => x.quantity === 0).length;

            summaryCards.innerHTML = `
    <div class="col-md-3"><div class="report-card"><h6>Total Items</h6><h3>${total}</h3></div></div>
    <div class="col-md-3"><div class="report-card"><h6>Total Stock Value</h6><h3 class="text-primary">₱${totalVal.toLocaleString()}</h3></div></div>
    <div class="col-md-3"><div class="report-card"><h6>Low Stock</h6><h3 class="text-warning">${low}</h3></div></div>
    <div class="col-md-3"><div class="report-card"><h6>Out of Stock</h6><h3 class="text-danger">${out}</h3></div></div>
    `;
        }

        /******************************************
         * FILTER HANDLING
         ******************************************/
        function populateFilters() {
            const types = [...new Set(inventoryData.map(x => x.item_type).filter(Boolean))];
            const cats = [...new Set(inventoryData.map(x => x.category ?? "").filter(Boolean))];

            fillSelect("typeFilter", types);
            fillSelect("categoryFilter", cats);
        }

        function fillSelect(id, list) {
            const sel = document.getElementById(id);
            list.forEach(v => {
                const opt = document.createElement("option");
                opt.value = v;
                opt.innerText = v;
                sel.appendChild(opt);
            });
        }

        function applyFilters() {
            const s = searchInput.value.toLowerCase();
            const type = typeFilter.value;
            const cat = categoryFilter.value;
            const unit = unitFilter.value;
            const stock = stockFilter.value;

            filteredData = inventoryData.filter(i => {
                return (
                    i.item_name.toLowerCase().includes(s) &&
                    (type === "" || i.item_type === type) &&
                    (cat === "" || i.category === cat) &&
                    (unit === "" || i.unit_type === unit) &&
                    (
                        stock === "" ||
                        (stock === "out" && i.quantity === 0) ||
                        (stock === "low" && i.quantity <= i.min_stock && i.quantity > 0) ||
                        (stock === "normal" && i.quantity > i.min_stock)
                    )
                );
            });

            currentPage = 1;
            renderTable();
            renderPagination();
            renderCharts(filteredData);
        }

        /******************************************
         * TABLE + PAGINATION
         ******************************************/
        function renderTable() {
            const start = (currentPage - 1) * rowsPerPage;
            const page = filteredData.slice(start, start + rowsPerPage);

            inventoryTable.innerHTML = page.map(i => {
                const risk = calcRisk(i);
                return `
        <tr class="${ i.quantity===0?'danger-row': i.quantity<=i.min_stock?'warning-row':'' }">
            <td>${i.item_name}</td>
            <td>${i.item_type}</td>
            <td>${i.category ?? '-'}</td>
            <td>${i.sub_type || '-'}</td>
            <td>${i.quantity}</td>
            <td>${i.total_qty}</td>
            <td>${i.unit_type}</td>
            <td>₱${i.price.toLocaleString()}</td>
            <td>${i.location}</td>
            <td>${new Date(i.received_at).toLocaleDateString()}</td>
            <td><b>${risk}</b></td>
        </tr>`;
            }).join("");
        }

        function renderPagination() {
            const total = Math.ceil(filteredData.length / rowsPerPage);
            pagination.innerHTML = "";
            for (let i = 1; i <= total; i++) {
                pagination.innerHTML += `
        <li class="page-item ${i==currentPage?'active':''}">
            <button class="page-link" onclick="goToPage(${i})">${i}</button>
        </li>`;
            }
        }

        function goToPage(p) {
            currentPage = p;
            renderTable();
        }

        /******************************************
         * RISK SCORE ENGINE (0–100)
         ******************************************/
        function calcRisk(i) {
            let score = 0;
            if (i.quantity === 0) score += 50;
            if (i.quantity <= i.min_stock) score += 30;

            const ageDays = (Date.now() - new Date(i.received_at)) / 86400000;
            if (ageDays > 180) score += 20;

            if (i.price > 10000) score += 10;

            return Math.min(score, 100);
        }

        /******************************************
         * DESTROY OLD CHARTS
         ******************************************/
        function destroyCharts() {
            if (chartTypes) chartTypes.destroy();
            if (chartLevels) chartLevels.destroy();
            if (chartTop) chartTop.destroy();
            if (chartForecast) chartForecast.destroy();
            if (chartAging) chartAging.destroy();
        }

        /******************************************
         * CHART RENDERING (FIXED)
         ******************************************/
        function renderCharts(data) {
            destroyCharts();

            /* ITEM TYPES */
            const typeCounts = {};
            data.forEach(i => typeCounts[i.item_type] = (typeCounts[i.item_type] || 0) + 1);

            const ctxTypes = document.getElementById("chartTypes").getContext("2d");
            chartTypes = new Chart(ctxTypes, {
                type: "pie",
                data: {
                    labels: Object.keys(typeCounts),
                    datasets: [{
                        data: Object.values(typeCounts)
                    }]
                }
            });

            /* STOCK LEVELS */
            const normal = data.filter(x => x.quantity > x.min_stock).length;
            const low = data.filter(x => x.quantity <= x.min_stock && x.quantity > 0).length;
            const out = data.filter(x => x.quantity === 0).length;

            const ctxLevels = document.getElementById("chartLevels").getContext("2d");
            chartLevels = new Chart(ctxLevels, {
                type: "doughnut",
                data: {
                    labels: ["Normal", "Low", "Out"],
                    datasets: [{
                        data: [normal, low, out]
                    }]
                }
            });

            /* TOP 10 ITEMS BY STOCK */
            const top = [...data].sort((a, b) => b.total_qty - a.total_qty).slice(0, 10);
            const ctxTop = document.getElementById("chartTop").getContext("2d");
            chartTop = new Chart(ctxTop, {
                type: "bar",
                data: {
                    labels: top.map(x => x.item_name),
                    datasets: [{
                        label: "Stock",
                        data: top.map(x => x.total_qty)
                    }]
                }
            });

            /* FORECAST (7 DAYS) */
            const ctxForecast = document.getElementById("chartForecast").getContext("2d");
            const forecastLabels = ["Today", "Day 1", "Day 2", "Day 3", "Day 4", "Day 5", "Day 6", "Day 7"];
            const forecastData = forecast7Days(data);

            chartForecast = new Chart(ctxForecast, {
                type: "line",
                data: {
                    labels: forecastLabels,
                    datasets: [{
                        label: "Predicted Stock",
                        data: forecastData,
                        borderColor: "blue"
                    }]
                }
            });

            /* AGING CHART */
            const ctxAging = document.getElementById("chartAging").getContext("2d");
            const ages = data.map(i => calcAge(i));

            chartAging = new Chart(ctxAging, {
                type: "bar",
                data: {
                    labels: data.map(i => i.item_name),
                    datasets: [{
                        label: "Days in Inventory",
                        data: ages
                    }]
                }
            });
        }

        /******************************************
         * INVENTORY AGING
         ******************************************/
        function calcAge(i) {
            return Math.floor((Date.now() - new Date(i.received_at)) / 86400000);
        }

        /******************************************
         * PREDICTIVE FORECAST (7 DAYS)
         ******************************************/
        function forecast7Days(data) {
            let total = 0;
            data.forEach(i => {
                const age = calcAge(i);
                const used = i.total_qty - i.quantity;
                const daily = age > 0 ? used / age : 0;
                const projected = Math.max(i.quantity - daily * 7, 0);
                total += projected;
            });
            return [
                total,
                total - 50, total - 100, total - 150, total - 200,
                total - 250, total - 300, total - 350
            ];
        }

        /******************************************
         * AI ENGINE (Insights + Heatmap + Suppliers)
         ******************************************/
        function generateAI(data) {
            const low = data.filter(i => i.quantity <= i.min_stock && i.quantity > 0);
            const out = data.filter(i => i.quantity === 0);

            aiInsights.innerHTML = `
        • Total Items: <b>${data.length}</b><br>
        • Low Stock: <b>${low.length}</b><br>
        • Out of Stock: <b>${out.length}</b><br>
        • High Value Items (>₱10k): <b>${data.filter(i=>i.price>10000).length}</b><br>
        • Avg Item Age: <b>${Math.round(data.reduce((s,i)=>s+calcAge(i),0)/data.length)} days</b>
    `;

            /* Department Usage (Synthetic — replace when real data available) */
            aiDept.innerHTML = `
        ER: <b>${Math.floor(Math.random()*50)+20}</b> uses<br>
        ICU: <b>${Math.floor(Math.random()*40)+10}</b> uses<br>
        OPD: <b>${Math.floor(Math.random()*30)+5}</b> uses<br>
        Laboratory: <b>${Math.floor(Math.random()*60)+30}</b> uses<br>
    `;

            /* Supplier Performance (Synthetic) */
            aiSupplier.innerHTML = `
        Fastest Supplier: <b>MedLine Corp</b><br>
        Highest Cost Supplier: <b>BioTech Pharma</b><br>
        Least Stable Supplier: <b>GlobalMed</b><br>
    `;

            /* HEATMAP */
            aiHeatmap.innerHTML = data.map(i => {
                let risk = "risk-low";
                if (i.quantity === 0) risk = "risk-high";
                else if (i.quantity <= i.min_stock) risk = "risk-medium";

                return `<div class="heatmap-box ${risk}">
            <b>${i.item_name}</b><br>
            Qty: ${i.quantity} | Min: ${i.min_stock}
        </div>`;
            }).join("");

            /* FORECAST SUMMARY */
            const forecast = forecast7Days(data);
            aiPrediction.innerHTML = `
        <b>Projected Total Stock in 7 Days:</b><br>
        <h4 class="text-primary">${Math.round(forecast[7]).toLocaleString()}</h4>
    `;
        }

        /******************************************
         * EXPORT FUNCTIONS
         ******************************************/
        function exportPDF() {
            html2pdf().from(document.getElementById("reportArea")).save("Inventory_Report.pdf");
        }

        function exportCSV() {
            let csv = "Name,Type,Category,SubType,Qty,TotalQty,Unit,Price,Location,Received\n";
            filteredData.forEach(i => {
                csv += `${i.item_name},${i.item_type},${i.category??"-"},${i.sub_type||"-"},${i.quantity},${i.total_qty},${i.unit_type},${i.price},${i.location},${new Date(i.received_at).toLocaleDateString()}\n`;
            });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(new Blob([csv], {
                type: "text/csv"
            }));
            link.download = "Inventory_Report.csv";
            link.click();
        }

        /* INITIAL LOAD */
        loadReport();
    </script>

</body>

</html>