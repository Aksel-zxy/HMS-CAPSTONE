<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Laboratory Performance Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f5f5f5;
        }

        .card {
            border-radius: 10px;
        }

        .loader {
            text-align: center;
            padding: 50px;
            font-size: 20px;
            color: gray;
        }
    </style>
</head>

<body>

    <div class="container mt-4">

        <h1 class="text-center mb-4">Laboratory Performance Report</h1>
        <h5 class="text-center text-muted">Auto-loaded from API Endpoints</h5>
        <hr>

        <!-- SUMMARY CARDS -->
        <div class="row g-3" id="summaryCards">
            <div class="loader">Loading data...</div>
        </div>

        <hr class="my-4">

        <!-- CHARTS -->
        <h3 class="mb-3">Statistical Charts</h3>
        <div class="row mb-4">
            <div class="col-md-6"><canvas id="testTypeChart"></canvas></div>
            <div class="col-md-6"><canvas id="normalRateChart"></canvas></div>
        </div>

        <hr>

        <!-- RESULTS TABLE -->
        <h3>Laboratory Results</h3>
        <div class="card p-3 mb-4">
            <table class="table table-striped" id="resultsTable">
                <thead>
                    <tr>
                        <th>Test Type</th>
                        <th>Patient</th>
                        <th>Findings</th>
                        <th>Processed By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <hr>

        <!-- INVENTORY -->
        <h3>Consumables Used</h3>
        <div class="card p-3 mb-4">
            <table class="table table-bordered" id="toolsTable">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Total Quantity</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <hr>

        <!-- VALIDATION -->
        <h3>Validation Summary</h3>
        <div class="card p-3">
            <table class="table table-bordered" id="validationTable">
                <thead>
                    <tr>
                        <th>ResultID</th>
                        <th>Test</th>
                        <th>Status</th>
                        <th>Validated By</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>

    <script>
        // ============================== API ENDPOINTS =================================

        const API_CT = "https://bsis-03.keikaizen.xyz/patient/lab_ct";
        const API_MRI = "https://bsis-03.keikaizen.xyz/patient/lab_mri";
        const API_TOOLS = "https://bsis-03.keikaizen.xyz/patient/lab_tools_used";
        const API_XRAY = "https://bsis-03.keikaizen.xyz/lab_xray";
        const API_RESULTS = "https://bsis-03.keikaizen.xyz/patient/lab_results";

        // ============================== FETCH FUNCTION =================================

        async function fetchData(url) {
            const res = await fetch(url);
            return res.json();
        }

        // ============================== MAIN LOADING ===================================

        async function loadDashboard() {
            const ct = await fetchData(API_CT);
            const mri = await fetchData(API_MRI);
            const tools = await fetchData(API_TOOLS);
            const xray = await fetchData(API_XRAY);
            const results = await fetchData(API_RESULTS);

            const allTests = [...ct, ...mri, ...xray];
            const normalCount = allTests.filter(t => t.findings?.trim() === "Normal").length;

            // ====================== SUMMARY CARDS ===========================
            document.getElementById("summaryCards").innerHTML = `
        <div class="col-md-3"><div class="card p-3 text-center"><h4>Total Tests</h4><h2>${allTests.length}</h2></div></div>
        <div class="col-md-3"><div class="card p-3 text-center"><h4>Normal</h4><h2>${normalCount}</h2></div></div>
        <div class="col-md-3"><div class="card p-3 text-center"><h4>Abnormal</h4><h2>${allTests.length - normalCount}</h2></div></div>
        <div class="col-md-3"><div class="card p-3 text-center"><h4>Validated</h4><h2>${results.length}</h2></div></div>
    `;

            // ====================== RESULTS TABLE ===========================
            const tbody = document.querySelector("#resultsTable tbody");
            allTests.forEach(t => {
                tbody.innerHTML += `
            <tr>
                <td>${t.testType}</td>
                <td>${t.patientID}</td>
                <td>${t.findings?.trim()}</td>
                <td>${t.processed_by}</td>
                <td>${new Date(t.created_at).toLocaleString()}</td>
            </tr>
        `;
            });

            // ====================== TOOLS USAGE =============================
            const toolsSummary = tools.reduce((acc, item) => {
                if (!acc[item.item_name]) acc[item.item_name] = {
                    qty: 0,
                    cost: 0
                };
                acc[item.item_name].qty += item.quantity;
                acc[item.item_name].cost += item.quantity * item.price;
                return acc;
            }, {});

            const toolsBody = document.querySelector("#toolsTable tbody");
            for (let item in toolsSummary) {
                toolsBody.innerHTML += `
            <tr>
                <td>${item}</td>
                <td>${toolsSummary[item].qty}</td>
                <td>₱${toolsSummary[item].cost.toLocaleString()}</td>
            </tr>
        `;
            }

            // ====================== VALIDATION TABLE ==========================
            const valBody = document.querySelector("#validationTable tbody");
            results.forEach(r => {
                valBody.innerHTML += `
            <tr>
                <td>${r.resultID}</td>
                <td>${r.result}</td>
                <td>${r.status}</td>
                <td>${r.validated_by}</td>
            </tr>
        `;
            });

            // ====================== CHARTS ==================================

            new Chart(document.getElementById('testTypeChart'), {
                type: 'bar',
                data: {
                    labels: ['CT', 'MRI', 'X-ray'],
                    datasets: [{
                        label: 'Number of Tests',
                        data: [ct.length, mri.length, xray.length],
                        borderWidth: 1
                    }]
                }
            });

            new Chart(document.getElementById('normalRateChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Normal', 'Abnormal'],
                    datasets: [{
                        data: [normalCount, allTests.length - normalCount]
                    }]
                }
            });
        }

        // Initialize dashboard
        loadDashboard();
    </script>

</body>

</html>