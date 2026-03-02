<?php
include 'header.php';

include '../../SQL/config.php';

if (!isset($_SESSION['report']) || $_SESSION['report'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Hospital Analytics Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- html2pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body {
            background: #f5f7fa;
            font-family: "Inter", sans-serif;
        }

        .page-container {
            margin-left: 270px;
            padding: 25px 30px;
        }

        .dashboard-section {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e3e7ee;
            padding: 25px;
            margin-bottom: 45px;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #0d6efd;
        }

        .chart-container {
            width: 100%;
            height: 350px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .quick-access-card {
            background: #ffffff;
            border: 1px solid #e3e7ee;
            border-radius: 12px;
            padding: 22px;
            text-align: center;
            transition: 0.2s;
            cursor: pointer;
        }

        .quick-access-card:hover {
            transform: translateY(-3px);
            border-color: #0d6efd;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.08);
        }

        .quick-access-icon {
            font-size: 32px;
            color: #0d6efd;
            margin-bottom: 6px;
        }

        .kpi-card {
            border-radius: 12px;
        }

        .nav-tile {
            background: #ffffff;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid #d9dee5;
            font-weight: 600;
            cursor: pointer;
            transition: 0.25s;
            text-align: center;
        }

        .nav-tile:hover {
            transform: translateY(-3px);
            border-color: #0d6efd;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, .08);
        }

        /* AI Chat Button */
        #aiChatBtn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: #0d6efd;
            color: white;
            border: none;
            padding: 14px 17px;
            border-radius: 50%;
            font-size: 19px;
            cursor: pointer;
            z-index: 9000;
        }

        #aiChatWindow {
            position: fixed;
            bottom: 90px;
            right: 25px;
            width: 340px;
            height: 420px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.25);
            z-index: 9001;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #aiChatWindow.hidden {
            display: none;
        }

        .ai-chat-header {
            background: #0d6efd;
            color: white;
            padding: 10px 12px;
            font-weight: 600;
        }

        .ai-chat-messages {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
            background: #f5f7fa;
        }

        .ai-msg {
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            max-width: 85%;
            font-size: .9rem;
        }

        .ai-msg.user {
            background: #d1eaff;
            margin-left: auto;
        }

        .ai-msg.bot {
            background: white;
            border: 1px solid #dce0e6;
            margin-right: auto;
        }

        .ai-chat-input {
            padding: 8px;
            border-top: 1px solid #dee2e6;
            background: white;
            display: flex;
            gap: 8px;
        }

        .ai-chat-input input {
            flex: 1;
            padding: 7px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .ai-chat-input button {
            background: #0d6efd;
            border: none;
            color: white;
            padding: 7px 12px;
            border-radius: 6px;
        }
    </style>
</head>

<body>

    <div class="d-flex">

        <!-- AI CHAT FLOATING BUTTON -->
        <button id="aiChatBtn" onclick="toggleAIChat()">💬</button>

        <!-- AI CHAT WINDOW -->
        <div id="aiChatWindow" class="hidden">
            <div class="ai-chat-header d-flex justify-content-between align-items-center">
                <span>AI Hospital Assistant</span>
                <button onclick="toggleAIChat()" style="background:none;border:none;color:white;font-size:18px;">✖</button>
            </div>
            <div id="aiChatMessages" class="ai-chat-messages"></div>
            <div class="ai-chat-input">
                <input id="aiChatInput" type="text" placeholder="Ask anything…"
                    onkeypress="if(event.key==='Enter') sendAIMessage()">
                <button onclick="sendAIMessage()">Send</button>
            </div>
        </div>

        <?php include 'sidebar.php'; ?>

        <!-- MAIN PAGE -->
        <div class="page-container">

            <h1 class="fw-bold mb-4">🏥 Hospital Analytics Dashboard</h1>

            <!-- ===================== QUICK ACCESS ====================== -->
            <div class="dashboard-section">
                <div class="section-title">🚀 Quick Access</div>

                <div class="grid-3">
                    <div class="quick-access-card" onclick="location.href='patient_census.php'">
                        <div class="quick-access-icon">🛏</div>
                        <div class="quick-access-label">Patient Census</div>
                    </div>

                    <div class="quick-access-card" onclick="location.href='staff_performance_and_attendance_report.php'">
                        <div class="quick-access-icon">👥</div>
                        <div class="quick-access-label">Staff Performance</div>
                    </div>

                    <div class="quick-access-card" onclick="location.href='year_attendance_report.php'">
                        <div class="quick-access-icon">📅</div>
                        <div class="quick-access-label">Year Attendance</div>
                    </div>

                    <div class="quick-access-card" onclick="location.href='year_beds_summary_report.php'">
                        <div class="quick-access-icon">🛌</div>
                        <div class="quick-access-label">Beds Summary</div>
                    </div>

                    <div class="quick-access-card" onclick="location.href='year_claim_report.php'">
                        <div class="quick-access-icon">🧾</div>
                        <div class="quick-access-label">Claim Report</div>
                    </div>

                    <div class="quick-access-card" onclick="location.href='year_payroll_summary.php'">
                        <div class="quick-access-icon">📄</div>
                        <div class="quick-access-label">Payroll Summary</div>
                    </div>

                    <div class="quick-access-card" onclick="location.href='year_pharmacy_sales_report.php'">
                        <div class="quick-access-icon">💊</div>
                        <div class="quick-access-label">Pharmacy Sales</div>
                    </div>

                    <div class="quick-access-card" onclick="location.href='yearly_billing_report.php'">
                        <div class="quick-access-icon">💰</div>
                        <div class="quick-access-label">Billing Report</div>
                    </div>

                    <div class="quick-access-card" onclick="location.href='inventory_report.php'">
                        <div class="quick-access-icon">📦</div>
                        <div class="quick-access-label">Inventory</div>
                    </div>
                </div>
            </div>

            <!-- ===================== OVERVIEW ====================== -->
            <div id="overview_section" class="dashboard-section mb-5">

                <div class="section-title mb-4">📌 Hospital Overview</div>

                <div id="overview_kpis" class="row g-4">

                    <div class="col-md-4">
                        <div class="p-4 text-center bg-white border rounded shadow-sm kpi-card">
                            <h6 class="text-muted mb-2">Attendance</h6>
                            <h2 class="fw-bold" id="kpi_attendance">Loading…</h2>
                        </div>
                    </div>

                    <!-- FINANCIAL STATUS KPI -->
                    <div class="col-md-4">
                        <div id="financial_kpi" class="p-4 text-center bg-white border rounded shadow-sm kpi-card"
                            style="transition:0.3s;">
                            <h6 class="text-muted mb-2">Financial Status</h6>
                            <h2 class="fw-bold" id="kpi_financial_status">Loading…</h2>
                            <p id="kpi_financial_insight" class="mt-2 mb-0 small"></p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-4 text-center bg-white border rounded shadow-sm kpi-card">
                            <h6 class="text-muted mb-2">Insurance Claims</h6>
                            <h2 class="fw-bold" id="kpi_claims">Loading…</h2>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-4 text-center bg-white border rounded shadow-sm kpi-card">
                            <h6 class="text-muted mb-2">Billing</h6>
                            <h2 class="fw-bold" id="kpi_billing">Loading…</h2>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-4 text-center bg-white border rounded shadow-sm kpi-card">
                            <h6 class="text-muted mb-2">Pharmacy Sales</h6>
                            <h2 class="fw-bold" id="kpi_pharmacy">Loading…</h2>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-4 text-center bg-white border rounded shadow-sm kpi-card">
                            <h6 class="text-muted mb-2">Payroll</h6>
                            <h2 class="fw-bold" id="kpi_payroll">Loading…</h2>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-4 text-center bg-white border rounded shadow-sm kpi-card">
                            <h6 class="text-muted mb-2">Inventory Items</h6>
                            <h2 class="fw-bold" id="kpi_inventory">Loading…</h2>
                        </div>
                    </div>

                </div>
                <!-- ===================== FINANCIAL OVERVIEW SECTION ====================== -->
                <div id="financial_section" class="dashboard-section mt-4">

                    <div class="section-title">💰 Financial Performance Overview</div>

                    <!-- FINANCIAL STATUS BOX -->
                    <div id="financial_status_box"
                        class="p-3 mb-4 text-center fw-bold shadow-sm"
                        style="border-radius:12px; font-size:22px; transition:0.3s;">
                        Loading financial status…
                    </div>

                    <!-- FINANCIAL KPI GRID -->
                    <div class="row g-4 mb-3">

                        <div class="col-md-4">
                            <div class="p-4 text-center bg-white border rounded shadow-sm">
                                <h6 class="text-muted mb-2">Total Revenue</h6>
                                <h2 class="fw-bold text-success">₱146,285.44</h2>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-4 text-center bg-white border rounded shadow-sm">
                                <h6 class="text-muted mb-2">Total Expenses</h6>
                                <h2 class="fw-bold text-danger">₱475,000.00</h2>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-4 text-center bg-white border rounded shadow-sm">
                                <h6 class="text-muted mb-2">Net Profit / Loss</h6>
                                <h2 id="financial_profit_value" class="fw-bold">Loading…</h2>
                            </div>
                        </div>

                    </div>

                    <!-- FINANCIAL CHARTS -->
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <canvas id="chart_fin_rev_exp"></canvas>
                        </div>
                        <div class="col-md-4">
                            <canvas id="chart_fin_breakdown"></canvas>
                        </div>
                        <div class="col-md-4">
                            <canvas id="chart_fin_trend"></canvas>
                        </div>
                    </div>

                    <!-- AI ANALYSIS -->
                    <div class="analysis-box shadow-sm mt-4 p-3 bg-white border rounded">
                        <h5 class="fw-bold mb-3">🧠 AI Financial Insight</h5>
                        <div id="financial_ai_analysis">Analyzing…</div>
                    </div>

                </div>

                <!-- ===================== LABORATORY PERFORMANCE SECTION ====================== -->
                <div id="lab_section" class="dashboard-section mt-4">

                    <div class="section-title">🧪 Laboratory Performance Report</div>

                    <!-- SUMMARY CARDS -->
                    <div class="row g-3 mb-4" id="lab_summary_cards">
                        <div class="text-center text-muted">Loading laboratory data...</div>
                    </div>

                    <!-- LAB CHARTS -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <canvas id="lab_test_type_chart"></canvas>
                        </div>
                        <div class="col-md-6">
                            <canvas id="lab_normal_rate_chart"></canvas>
                        </div>
                    </div>

                    <!-- RESULTS TABLE -->
                    <h5 class="mt-4">Test Results</h5>
                    <div class="card p-3 mb-4">
                        <table class="table table-striped" id="lab_results_table">
                            <thead>
                                <tr>
                                    <th>Test</th>
                                    <th>Patient</th>
                                    <th>Findings</th>
                                    <th>Processed By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <!-- TOOLS USED -->
                    <h5>Consumables Used</h5>
                    <div class="card p-3 mb-4">
                        <table class="table table-bordered" id="lab_tools_table">
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

                    <!-- VALIDATION -->
                    <h5>Validation Summary</h5>
                    <div class="card p-3 mb-4">
                        <table class="table table-bordered" id="lab_validation_table">
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
                <!-- ===================== ANALYTICS GRID ====================== -->
                <div class="grid-2">

                    <!-- ========== ATTENDANCE SECTION ========== -->
                    <div id="attendance_section" class="dashboard-section">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>🟦 Employee Attendance</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportAttendanceCSV()">Export CSV</button>
                        </div>

                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>

                        <div id="attendance_stats" class="mt-3"></div>
                    </div>

                    <!-- ========== STAFF PERFORMANCE SECTION ========== -->
                    <div id="staff_section" class="dashboard-section">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>🟩 Staff Performance</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportStaffCSV()">Export CSV</button>
                        </div>

                        <div class="chart-container">
                            <canvas id="staffAvgChart"></canvas>
                        </div>

                        <div id="staff_stats" class="mt-3"></div>
                    </div>

                    <!-- ========== INSURANCE CLAIMS SECTION ========== -->
                    <div id="insurance_section" class="dashboard-section">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>🟧 Insurance Claims</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportInsuranceCSV()">Export CSV</button>
                        </div>

                        <div class="chart-container">
                            <canvas id="insuranceChart"></canvas>
                        </div>

                        <div id="insurance_stats" class="mt-3"></div>
                    </div>

                    <!-- ========== BILLING SUMMARY SECTION ========== -->
                    <div id="billing_section" class="dashboard-section">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>💰 Billing Summary</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportBillingCSV()">Export CSV</button>
                        </div>

                        <div class="chart-container">
                            <canvas id="billingChart"></canvas>
                        </div>

                        <div id="billing_stats" class="mt-3"></div>
                    </div>

                    <!-- ========== PHARMACY SALES SECTION ========== -->
                    <div id="pharmacy_section" class="dashboard-section">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>💊 Pharmacy Sales</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportPharmacyCSV()">Export CSV</button>
                        </div>

                        <div class="chart-container">
                            <canvas id="pharmacyChart"></canvas>
                        </div>

                        <div id="pharmacy_stats" class="mt-3"></div>
                    </div>

                    <!-- ========== PATIENT CENSUS SECTION ========== -->
                    <div id="census_section" class="dashboard-section">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>🛏 Patient Census</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportCensusCSV()">Export CSV</button>
                        </div>

                        <div class="chart-container">
                            <canvas id="censusChart"></canvas>
                        </div>

                        <div id="census_stats" class="mt-3"></div>
                    </div>

                    <!-- ========== PAYROLL SUMMARY SECTION ========== -->
                    <div id="payroll_section" class="dashboard-section">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>📄 Payroll Summary</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportPayrollCSV()">Export CSV</button>
                        </div>

                        <div class="chart-container">
                            <canvas id="payrollChart"></canvas>
                        </div>

                        <div id="payroll_stats" class="mt-3"></div>
                    </div>

                    <!-- ========== INVENTORY SECTION ========== -->
                    <div id="inventory_section" class="dashboard-section">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>📦 Inventory Status</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportInventoryCSV()">Export CSV</button>
                        </div>

                        <div class="chart-container">
                            <canvas id="inventoryChart"></canvas>
                        </div>

                        <div id="inventory_stats" class="mt-3"></div>
                    </div>

                </div>
                <!-- END GRID -->
                <script>
                    /* ============================================================
   PART 3 — API ROUTES + FETCH WRAPPERS + MAIN LOADER
============================================================ */

                    /* ------------------------------------------
                       DEFAULT DATE RANGE (2025 → Feb 2026)
                    ------------------------------------------ */
                    const DEFAULTS = {
                        startDate: "2025-01-01",
                        endDate: "2026-02-28",

                        startMonth: 1,
                        startYear: 2025,
                        endMonth: 2,
                        endYear: 2026
                    };

                    /* ------------------------------------------
                       API ROUTES (AUTO-GENERATED BASED ON DEFAULTS)
                    ------------------------------------------ */
                    const API = {

                        attendance: () =>
                            `https://bsis-03.keikaizen.xyz/employee/monthAttendanceRangeQueryReport?start=${DEFAULTS.startMonth}&startYear=${DEFAULTS.startYear}&endMonth=${DEFAULTS.endMonth}&endYear=${DEFAULTS.endYear}`,

                        staffPerformance: () =>
                            `https://bsis-03.keikaizen.xyz/employee/staffPerformanceAndAttendanceReport?start=${DEFAULTS.startDate}&end=${DEFAULTS.endDate}`,

                        insurance: () =>
                            `https://bsis-03.keikaizen.xyz/insurance/monthInsuranceClaimRangeQuery?start=${DEFAULTS.startDate}&end=${DEFAULTS.endDate}`,

                        billing: () =>
                            `https://bsis-03.keikaizen.xyz/journal/monthBillingRangeReport?start=${DEFAULTS.startMonth}&startYear=${DEFAULTS.startYear}&endMonth=${DEFAULTS.endMonth}&endYear=${DEFAULTS.endYear}`,

                        pharmacy: () =>
                            `https://bsis-03.keikaizen.xyz/journal/monthPharmacyRangeReport?start=${DEFAULTS.startMonth}&startYear=${DEFAULTS.startYear}&endMonth=${DEFAULTS.endMonth}&endYear=${DEFAULTS.endYear}`,

                        census: () =>
                            `https://bsis-03.keikaizen.xyz/patient/getCensus?startDate=${DEFAULTS.startDate}&endDate=${DEFAULTS.endDate}`,

                        payroll: () =>
                            `https://bsis-03.keikaizen.xyz/payroll/monthPayrollRangeQueryAsync?startmonth=${DEFAULTS.startMonth}&startyear=${DEFAULTS.startYear}&endmonth=${DEFAULTS.endMonth}&endyear=${DEFAULTS.endYear}`,

                        inventory: () =>
                            `https://bsis-03.keikaizen.xyz/property/inventoryReport`,

                        lab: {
                            ct: "https://bsis-03.keikaizen.xyz/patient/lab_ct",
                            mri: "https://bsis-03.keikaizen.xyz/patient/lab_mri",
                            tools: "https://bsis-03.keikaizen.xyz/patient/lab_tools_used",
                            xray: "https://bsis-03.keikaizen.xyz/patient/lab_xray",
                            results: "https://bsis-03.keikaizen.xyz/patient/lab_results"
                        }
                    };

                    function renderLabSection() {
                        const {
                            ct,
                            mri,
                            tools,
                            xray,
                            results
                        } = window.LAB_DATA;

                        const allTests = [...ct, ...mri, ...xray];
                        const normalCount = allTests.filter(t => (t.findings || "").trim() === "Normal").length;

                        // SUMMARY CARDS
                        document.getElementById("lab_summary_cards").innerHTML = `
        <div class="col-md-3"><div class="card p-3 text-center"><h6>Total Tests</h6><h2>${allTests.length}</h2></div></div>
        <div class="col-md-3"><div class="card p-3 text-center"><h6>Normal</h6><h2>${normalCount}</h2></div></div>
        <div class="col-md-3"><div class="card p-3 text-center"><h6>Abnormal</h6><h2>${allTests.length - normalCount}</h2></div></div>
        <div class="col-md-3"><div class="card p-3 text-center"><h6>Validated</h6><h2>${results.length}</h2></div></div>
    `;

                        // RESULTS TABLE
                        const tbody = document.querySelector("#lab_results_table tbody");
                        tbody.innerHTML = "";
                        allTests.forEach(t => {
                            tbody.innerHTML += `
            <tr>
                <td>${t.testType}</td>
                <td>${t.patientID}</td>
                <td>${(t.findings || "").trim()}</td>
                <td>${t.processed_by}</td>
                <td>${new Date(t.created_at).toLocaleString()}</td>
            </tr>
        `;
                        });

                        // TOOLS TABLE
                        const toolsSummary = tools.reduce((a, i) => {
                            if (!a[i.item_name]) a[i.item_name] = {
                                qty: 0,
                                cost: 0
                            };
                            a[i.item_name].qty += i.quantity;
                            a[i.item_name].cost += i.quantity * i.price;
                            return a;
                        }, {});

                        const toolsBody = document.querySelector("#lab_tools_table tbody");
                        toolsBody.innerHTML = "";
                        for (let item in toolsSummary) {
                            toolsBody.innerHTML += `
            <tr>
                <td>${item}</td>
                <td>${toolsSummary[item].qty}</td>
                <td>₱${toolsSummary[item].cost}</td>
            </tr>
        `;
                        }

                        // VALIDATION
                        const valBody = document.querySelector("#lab_validation_table tbody");
                        valBody.innerHTML = "";
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

                        // MAKE CHARTS
                        renderLabCharts(ct, mri, xray, normalCount, allTests.length);
                    }

                    /* ------------------------------------------
                       SAFE FETCH WRAPPER
                    ------------------------------------------ */
                    async function fetchJSON(url, label) {
                        try {
                            const res = await fetch(url);

                            if (!res.ok) {
                                throw new Error(`${label} API failed with status ${res.status}`);
                            }

                            return await res.json();

                        } catch (e) {
                            console.error(`❌ Error loading ${label}:`, e);
                            return null;
                        }
                    }

                    /* ------------------------------------------
                       MAIN DASHBOARD LOADER
                    ------------------------------------------ */
                    /* ------------------------------------------
   MAIN DASHBOARD LOADER (FIXED)
------------------------------------------ */
                    async function loadDashboard() {

                        // Load all standard dashboard modules
                        const [
                            attendanceData,
                            staffData,
                            insuranceData,
                            billingData,
                            pharmacyData,
                            censusData,
                            payrollData,
                            inventoryData
                        ] = await Promise.all([
                            fetchJSON(API.attendance(), "Attendance"),
                            fetchJSON(API.staffPerformance(), "Staff Performance"),
                            fetchJSON(API.insurance(), "Insurance Claims"),
                            fetchJSON(API.billing(), "Billing"),
                            fetchJSON(API.pharmacy(), "Pharmacy"),
                            fetchJSON(API.census(), "Patient Census"),
                            fetchJSON(API.payroll(), "Payroll"),
                            fetchJSON(API.inventory(), "Inventory")
                        ]);

                        // Store core dashboard data
                        window.HOSPITAL_DATA = {
                            attendanceData,
                            staffData,
                            insuranceData,
                            billingData,
                            pharmacyData,
                            censusData,
                            payrollData,
                            inventoryData
                        };

                        // Render normal dashboard sections
                        renderAllSections();

                        // Financial overview section
                        renderFinancialSection();

                        /* ======================================================
                           🧪 LOAD LABORATORY PERFORMANCE REPORT (NEW SECTION)
                        ====================================================== */
                        const labCT = await fetchJSON(API.lab.ct, "Lab CT");
                        const labMRI = await fetchJSON(API.lab.mri, "Lab MRI");
                        const labTools = await fetchJSON(API.lab.tools, "Lab Tools Used");
                        const labXray = await fetchJSON(API.lab.xray, "Lab X-ray");
                        const labResults = await fetchJSON(API.lab.results, "Lab Results");

                        // Store lab data globally
                        window.LAB_DATA = {
                            ct: labCT || [],
                            mri: labMRI || [],
                            tools: labTools || [],
                            xray: labXray || [],
                            results: labResults || []
                        };

                        // Render the Laboratory Performance dashboard section
                        renderLabSection();
                    }

                    /* ------------------------------------------
                       AUTOLOAD DASHBOARD ON PAGE OPEN
                    ------------------------------------------ */
                    window.onload = () => loadDashboard();
                    /* ============================================================
   PART 4 — ALL RENDER FUNCTIONS
============================================================ */

                    /* Chart registry so we can destroy previous charts */
                    let charts = {};

                    function resetChart(id) {
                        if (charts[id]) charts[id].destroy();
                    }

                    /* ==========================================
                       4.1 — OVERVIEW KPI SECTION
                    ========================================== */
                    function renderOverview() {
                        const d = window.HOSPITAL_DATA;

                        /* -------------------------------
                           EXISTING KPI VALUES
                        --------------------------------*/
                        document.getElementById("kpi_attendance").innerText =
                            d.attendanceData?.present ?? "0";

                        document.getElementById("kpi_claims").innerText =
                            d.insuranceData?.total_claims ?? "0";

                        document.getElementById("kpi_billing").innerText =
                            "₱" + (d.billingData?.total_billed ?? 0).toLocaleString();

                        document.getElementById("kpi_pharmacy").innerText =
                            "₱" + (d.pharmacyData?.totalSales ?? 0).toLocaleString();

                        document.getElementById("kpi_payroll").innerText =
                            "₱" + (d.payrollData?.total_net_pay ?? 0).toLocaleString();

                        document.getElementById("kpi_inventory").innerText =
                            d.inventoryData?.length ?? "0";

                        /* ========================================================
                           🔥 FINANCIAL STATUS (PROFIT / LOSS) — NEW FEATURE
                        =========================================================*/

                        // Fixed values from your computed financial report
                        const FIN_REVENUE = 146285.44;
                        const FIN_EXPENSES = 475000.00;
                        const FIN_PROFIT = FIN_REVENUE - FIN_EXPENSES;

                        // Target elements
                        const finStatusEl = document.getElementById("kpi_financial_status");
                        const finCard = document.getElementById("financial_kpi");
                        const finInsight = document.getElementById("kpi_financial_insight");

                        if (!finStatusEl || !finCard || !finInsight) return; // safety fallback

                        /* -------------------------------
                           PROFITABLE
                        --------------------------------*/
                        if (FIN_PROFIT >= 0) {
                            finStatusEl.textContent = "₱" + FIN_PROFIT.toLocaleString(undefined, {
                                minimumFractionDigits: 2
                            });
                            finStatusEl.style.color = "green";

                            finCard.style.boxShadow = "0 0 18px rgba(0, 255, 0, 0.45)";
                            finCard.style.borderColor = "green";

                            finInsight.innerHTML = `
            🟢 <b>Profitable</b><br>
            The hospital generated more income than expenses this period.
        `;
                        }

                        /* -------------------------------
                           LOSS
                        --------------------------------*/
                        else {
                            finStatusEl.textContent = "-₱" + Math.abs(FIN_PROFIT).toLocaleString(undefined, {
                                minimumFractionDigits: 2
                            });
                            finStatusEl.style.color = "red";

                            finCard.style.boxShadow = "0 0 18px rgba(255, 0, 0, 0.45)";
                            finCard.style.borderColor = "red";

                            finInsight.innerHTML = `
            🔴 <b>Operating at a Loss</b><br>
            High ICU costs and maintenance expenses exceed current revenue.
        `;
                        }
                    }
                    /* ==========================================
                       4.2 — ATTENDANCE SECTION
                    ========================================== */
                    function renderAttendance() {
                        const data = window.HOSPITAL_DATA.attendanceData;
                        if (!data?.months) return;

                        const labels = data.months.map(m => `M${m.month}`);
                        const present = data.months.map(m => m.present);
                        const absent = data.months.map(m => m.absent);
                        const late = data.months.map(m => m.late);

                        resetChart("attendanceChart");

                        charts["attendanceChart"] = new Chart(attendanceChart, {
                            type: "bar",
                            data: {
                                labels,
                                datasets: [{
                                        label: "Present",
                                        data: present,
                                        backgroundColor: "#0d6efd"
                                    },
                                    {
                                        label: "Absent",
                                        data: absent,
                                        backgroundColor: "#dc3545"
                                    },
                                    {
                                        label: "Late",
                                        data: late,
                                        backgroundColor: "#ffc107"
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });

                        document.getElementById("attendance_stats").innerHTML = `
        <b>Total Present:</b> ${data.present}<br>
        <b>Total Absent:</b> ${data.absent}<br>
        <b>Total Late:</b> ${data.late}
    `;
                    }

                    /* ==========================================
                       4.3 — STAFF PERFORMANCE SECTION
                    ========================================== */
                    function renderStaffPerformance() {
                        const data = window.HOSPITAL_DATA.staffData;
                        if (!data) return;

                        const labels = data.map(d => d.department);
                        const avgScore = data.map(d => d.departmentEvaluationAverageScore);

                        resetChart("staffAvgChart");

                        charts["staffAvgChart"] = new Chart(staffAvgChart, {
                            type: "bar",
                            data: {
                                labels,
                                datasets: [{
                                    label: "Avg Score",
                                    data: avgScore,
                                    backgroundColor: "#20c997"
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });

                        const top = data.reduce((a, b) =>
                            a.departmentEvaluationAverageScore > b.departmentEvaluationAverageScore ? a : b
                        );

                        document.getElementById("staff_stats").innerHTML = `
        <b>Top Department:</b> ${top.department}<br>
        <b>Score:</b> ${top.departmentEvaluationAverageScore.toFixed(2)}
    `;
                    }

                    /* ============================================================
   LAB CHARTS — REQUIRED FOR LAB DASHBOARD
============================================================ */
                    function renderLabCharts(ct, mri, xray, normalCount, totalTests) {

                        /* -------------------------
                           DESTROY OLD CHARTS FIRST
                        --------------------------*/
                        if (charts["labTestTypeChart"]) charts["labTestTypeChart"].destroy();
                        if (charts["labNormalRateChart"]) charts["labNormalRateChart"].destroy();

                        /* -------------------------
                           CHART 1 — Test Type Count
                        --------------------------*/
                        const ctx1 = document.getElementById("lab_test_type_chart");
                        if (ctx1) {
                            charts["labTestTypeChart"] = new Chart(ctx1, {
                                type: "bar",
                                data: {
                                    labels: ["CT Scan", "MRI", "X-ray"],
                                    datasets: [{
                                        label: "Number of Tests",
                                        data: [ct.length, mri.length, xray.length],
                                        backgroundColor: ["#0d6efd", "#6610f2", "#20c997"]
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false
                                }
                            });
                        }

                        /* -------------------------
                           CHART 2 — Normal vs Abnormal
                        --------------------------*/
                        const ctx2 = document.getElementById("lab_normal_rate_chart");
                        if (ctx2) {
                            charts["labNormalRateChart"] = new Chart(ctx2, {
                                type: "doughnut",
                                data: {
                                    labels: ["Normal", "Abnormal"],
                                    datasets: [{
                                        data: [normalCount, totalTests - normalCount],
                                        backgroundColor: ["#28a745", "#dc3545"]
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false
                                }
                            });
                        }
                    }

                    /* ==========================================
                       4.4 — INSURANCE CLAIMS SECTION
                    ========================================== */
                    function renderInsurance() {
                        const data = window.HOSPITAL_DATA.insuranceData;
                        if (!data?.months) return;

                        const labels = data.months.map(m => `M${m.month}`);
                        const approved = data.months.map(m => m.total_approved_claims);
                        const denied = data.months.map(m => m.total_denied_claims);

                        resetChart("insuranceChart");

                        charts["insuranceChart"] = new Chart(insuranceChart, {
                            type: "line",
                            data: {
                                labels,
                                datasets: [{
                                        label: "Approved",
                                        data: approved,
                                        borderColor: "#198754"
                                    },
                                    {
                                        label: "Denied",
                                        data: denied,
                                        borderColor: "#dc3545"
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });

                        document.getElementById("insurance_stats").innerHTML = `
        <b>Total Claims:</b> ${data.total_claims}<br>
        <b>Approved:</b> ${data.total_approved_claims}<br>
        <b>Denied:</b> ${data.total_denied_claims}
    `;
                    }

                    /* ==========================================
                       4.5 — BILLING SECTION
                    ========================================== */
                    function renderBilling() {
                        const data = window.HOSPITAL_DATA.billingData;
                        if (!data?.months) return;

                        const labels = data.months.map(m => `M${m.month}`);
                        const billed = data.months.map(m => m.total_billed);
                        const paid = data.months.map(m => m.total_paid);

                        resetChart("billingChart");

                        charts["billingChart"] = new Chart(billingChart, {
                            type: "bar",
                            data: {
                                labels,
                                datasets: [{
                                        label: "Billed",
                                        data: billed,
                                        backgroundColor: "#0d6efd"
                                    },
                                    {
                                        label: "Paid",
                                        data: paid,
                                        backgroundColor: "#20c997"
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });

                        document.getElementById("billing_stats").innerHTML = `
        <b>Total Billed:</b> ₱${data.total_billed.toLocaleString()}<br>
        <b>Total Paid:</b> ₱${data.total_paid.toLocaleString()}<br>
        <b>Pending:</b> ₱${data.total_pending_amount.toLocaleString()}
    `;
                    }

                    /* ==========================================
                       4.6 — PHARMACY SALES SECTION
                    ========================================== */
                    function renderPharmacy() {
                        const data = window.HOSPITAL_DATA.pharmacyData;
                        if (!data?.months) return;

                        const labels = data.months.map(m => `M${m.month}`);
                        const sales = data.months.map(m => m.totalSales);

                        resetChart("pharmacyChart");

                        charts["pharmacyChart"] = new Chart(pharmacyChart, {
                            type: "line",
                            data: {
                                labels,
                                datasets: [{
                                    label: "Sales",
                                    data: sales,
                                    borderColor: "#6610f2"
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });

                        document.getElementById("pharmacy_stats").innerHTML = `
        <b>Total Sales:</b> ₱${data.totalSales.toLocaleString()}<br>
        <b>Top Item:</b> ${data.topSellingItem}
    `;
                    }

                    /* ==========================================
                       4.7 — PATIENT CENSUS SECTION
                    ========================================== */
                    function renderCensus() {
                        const data = window.HOSPITAL_DATA.censusData;
                        if (!Array.isArray(data)) return;

                        const categoryMap = {};
                        data.forEach(x => {
                            const key = x.condition_name || "Unspecified";
                            categoryMap[key] = (categoryMap[key] || 0) + 1;
                        });

                        resetChart("censusChart");

                        charts["censusChart"] = new Chart(censusChart, {
                            type: "doughnut",
                            data: {
                                labels: Object.keys(categoryMap),
                                datasets: [{
                                    data: Object.values(categoryMap),
                                    backgroundColor: ["#0d6efd", "#6f42c1", "#20c997", "#ffc107", "#dc3545"]
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });

                        document.getElementById("census_stats").innerHTML = `
        <b>Total Patients Recorded:</b> ${data.length}
    `;
                    }

                    /* ==========================================
                       4.8 — PAYROLL SECTION
                    ========================================== */
                    function renderPayroll() {
                        const data = window.HOSPITAL_DATA.payrollData;
                        if (!data?.months) return;

                        const labels = data.months.map(m => `M${m.month}`);
                        const netPay = data.months.map(m => m.total_net_pay);

                        resetChart("payrollChart");

                        charts["payrollChart"] = new Chart(payrollChart, {
                            type: "bar",
                            data: {
                                labels,
                                datasets: [{
                                    label: "Net Pay",
                                    data: netPay,
                                    backgroundColor: "#fd7e14"
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });

                        document.getElementById("payroll_stats").innerHTML = `
        <b>Total Net Pay:</b> ₱${data.total_net_pay.toLocaleString()}
    `;
                    }

                    /* ==========================================
                       4.9 — INVENTORY SECTION
                    ========================================== */
                    function renderInventory() {
                        const data = window.HOSPITAL_DATA.inventoryData;
                        if (!data) return;

                        const labels = data.map(x => x.item_name);
                        const qty = data.map(x => x.quantity);

                        resetChart("inventoryChart");

                        charts["inventoryChart"] = new Chart(inventoryChart, {
                            type: "bar",
                            data: {
                                labels,
                                datasets: [{
                                    label: "Stock Qty",
                                    data: qty,
                                    backgroundColor: "#6c757d"
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });

                        const lowStock = data.filter(x => x.quantity <= x.min_stock).length;

                        document.getElementById("inventory_stats").innerHTML = `
        <b>Total Items:</b> ${data.length}<br>
        <b>Low Stock Items:</b> ${lowStock}
    `;
                    }

                    /* ==========================================
   COMBINED RENDER FUNCTION (REQUIRED)
========================================== */
                    function renderAllSections() {
                        renderOverview();
                        renderAttendance();
                        renderStaffPerformance();
                        renderInsurance();
                        renderBilling();
                        renderPharmacy();
                        renderCensus();
                        renderPayroll();
                        renderInventory();
                    }

                    /* ============================================================
   PART 5 — AI ASSISTANT LOGIC
============================================================ */

                    /* ------------------------------------------
                       TOGGLE CHAT WINDOW
                    ------------------------------------------ */
                    function toggleAIChat() {
                        const win = document.getElementById("aiChatWindow");
                        win.classList.toggle("hidden");
                    }

                    /* ------------------------------------------
                       ADD CHAT MESSAGE BUBBLE
                    ------------------------------------------ */
                    function appendChatBubble(text, sender) {
                        const box = document.getElementById("aiChatMessages");

                        const bubble = document.createElement("div");
                        bubble.className = "ai-msg " + sender;
                        bubble.innerHTML = text;

                        box.appendChild(bubble);
                        box.scrollTop = box.scrollHeight;
                    }

                    /* ------------------------------------------
                       SEND USER MESSAGE
                    ------------------------------------------ */
                    function sendAIMessage() {
                        const input = document.getElementById("aiChatInput");
                        const text = input.value.trim();

                        if (!text) return;

                        appendChatBubble(text, "user");
                        input.value = "";

                        aiRespond(text);
                    }

                    /* ------------------------------------------
                       AI RESPONSE ENGINE
                    ------------------------------------------ */
                    function aiRespond(message) {
                        appendChatBubble("Analyzing hospital data…", "bot");

                        setTimeout(() => {
                            const D = window.HOSPITAL_DATA;
                            const msg = message.toLowerCase();
                            let reply = "";

                            /* 🔵 Attendance */
                            if (msg.includes("attendance")) {
                                reply = `
📊 <b>Attendance Summary</b><br><br>
• Present: <b>${D.attendanceData?.present ?? 0}</b><br>
• Late: <b>${D.attendanceData?.late ?? 0}</b><br>
• Absent: <b>${D.attendanceData?.absent ?? 0}</b><br><br>
${D.attendanceData?.late > 10 
    ? "⚠ High lateness detected" 
    : "✔ Attendance is stable"}
            `;
                            }

                            /* 🟩 Staff Performance */
                            else if (msg.includes("staff") || msg.includes("performance")) {
                                const best = D.staffData.reduce((a, b) =>
                                    a.departmentEvaluationAverageScore > b.departmentEvaluationAverageScore ? a : b
                                );

                                reply = `
🟩 <b>Staff Performance</b><br><br>
• Top Department: <b>${best.department}</b><br>
• Score: <b>${best.departmentEvaluationAverageScore.toFixed(2)}</b><br>
Would you like department comparison?
            `;
                            }

                            /* 🟧 Insurance Claims */
                            else if (msg.includes("insurance") || msg.includes("claim")) {
                                const d = D.insuranceData;
                                reply = `
🟧 <b>Insurance Claims</b><br><br>
• Approved: <b>${d.total_approved_claims}</b><br>
• Denied: <b>${d.total_denied_claims}</b><br>
• Total Claims: <b>${d.total_claims}</b><br>
            `;
                            }

                            /* 💰 Billing */
                            else if (msg.includes("billing") || msg.includes("paid") || msg.includes("bill")) {
                                const d = D.billingData;
                                reply = `
💰 <b>Billing Summary</b><br><br>
• Total Billed: ₱${d.total_billed.toLocaleString()}<br>
• Total Paid: ₱${d.total_paid.toLocaleString()}<br>
• Pending: ₱${d.total_pending_amount.toLocaleString()}<br>
            `;
                            }

                            /* 💊 Pharmacy */
                            else if (msg.includes("pharmacy") || msg.includes("medicine") || msg.includes("sales")) {
                                const d = D.pharmacyData;
                                reply = `
💊 <b>Pharmacy Sales</b><br><br>
• Total Sales: ₱${d.totalSales.toLocaleString()}<br>
• Top Item: <b>${d.topSellingItem}</b><br>
            `;
                            }

                            /* 🛏 Patient Census */
                            else if (msg.includes("census") || msg.includes("patient")) {
                                const active = D.censusData.filter(x => x.released_date === null).length;

                                reply = `
🛏 <b>Patient Census</b><br><br>
• Total Patients Recorded: <b>${D.censusData.length}</b><br>
• Active Beds: <b>${active}</b><br>
            `;
                            }

                            /* 📄 Payroll */
                            else if (msg.includes("payroll") || msg.includes("salary")) {
                                const d = D.payrollData;

                                reply = `
📄 <b>Payroll Summary</b><br><br>
• Gross Pay: ₱${d.total_gross_pay.toLocaleString()}<br>
• Net Pay: ₱${d.total_net_pay.toLocaleString()}<br>
            `;
                            }

                            /* 📦 Inventory */
                            else if (msg.includes("inventory") || msg.includes("stock")) {
                                const low = D.inventoryData.filter(x => x.quantity <= x.min_stock).length;

                                reply = `
📦 <b>Inventory Status</b><br><br>
• Total Items: <b>${D.inventoryData.length}</b><br>
• Low Stock Items: <b>${low}</b><br>
            `;
                            }

                            /* Default Help */
                            else {
                                reply = `
I can help you analyze:<br><br>
• Attendance<br>
• Staff Performance<br>
• Insurance Claims<br>
• Billing<br>
• Pharmacy<br>
• Patient Census<br>
• Payroll<br>
• Inventory<br><br>
Try asking:<br>
<b>“Summarize the whole hospital status.”</b><br>
<b>“What department performs best?”</b><br>
<b>“Show me billing insights.”</b>
            `;
                            }

                            appendChatBubble(reply, "bot");
                        }, 600);
                    }

                    /* ============================================================
   PART 6 — EXPORT ENGINE (CSV, PDF, ZIP)
============================================================ */

                    /* ------------------------------------------
                       EXPORT DASHBOARD AS PDF (html2pdf)
                    ------------------------------------------ */
                    async function exportDashboardPDF() {

                        const chatBtn = document.getElementById("aiChatBtn");
                        if (chatBtn) chatBtn.style.display = "none"; // hide from screenshot

                        const element = document.body;

                        const opt = {
                            margin: 0.4,
                            filename: `Hospital_Dashboard_${DEFAULTS.startDate}_to_${DEFAULTS.endDate}.pdf`,
                            html2canvas: {
                                scale: 2,
                                scrollY: 0
                            },
                            jsPDF: {
                                unit: 'in',
                                format: 'A4',
                                orientation: 'portrait'
                            }
                        };

                        await html2pdf().from(element).set(opt).save();

                        if (chatBtn) chatBtn.style.display = "block";
                    }

                    /* ------------------------------------------
                       GENERIC CSV EXPORTER
                    ------------------------------------------ */
                    function exportCSV(filename, rows) {
                        if (!rows || rows.length === 0) {
                            alert("No data available to export.");
                            return;
                        }

                        let csv = "";

                        // CSV Header
                        csv += Object.keys(rows[0]).join(",") + "\n";

                        // CSV Rows
                        rows.forEach(r => {
                            csv += Object.values(r).join(",") + "\n";
                        });

                        const blob = new Blob([csv], {
                            type: "text/csv"
                        });
                        const link = document.createElement("a");

                        link.href = URL.createObjectURL(blob);
                        link.download = filename;
                        link.click();
                    }

                    /* ------------------------------------------
                       PER-MODULE CSV EXPORT SLOTS
                    ------------------------------------------ */
                    function exportAttendanceCSV() {
                        exportCSV("Attendance_Report.csv", window.HOSPITAL_DATA.attendanceData?.months || []);
                    }

                    function exportStaffCSV() {
                        exportCSV("Staff_Performance.csv", window.HOSPITAL_DATA.staffData || []);
                    }

                    function exportInsuranceCSV() {
                        exportCSV("Insurance_Claims.csv", window.HOSPITAL_DATA.insuranceData?.months || []);
                    }

                    function exportBillingCSV() {
                        exportCSV("Billing_Report.csv", window.HOSPITAL_DATA.billingData?.months || []);
                    }

                    function exportPharmacyCSV() {
                        exportCSV("Pharmacy_Sales.csv", window.HOSPITAL_DATA.pharmacyData?.months || []);
                    }

                    function exportCensusCSV() {
                        exportCSV("Patient_Census.csv", window.HOSPITAL_DATA.censusData || []);
                    }

                    function exportPayrollCSV() {
                        exportCSV("Payroll_Report.csv", window.HOSPITAL_DATA.payrollData?.months || []);
                    }

                    function exportInventoryCSV() {
                        exportCSV("Inventory_Report.csv", window.HOSPITAL_DATA.inventoryData || []);
                    }

                    /* ------------------------------------------
                       ZIP EXPORT — ALL REPORTS IN ONE ZIP FILE
                    ------------------------------------------ */
                    async function exportAllToZip() {

                        if (typeof JSZip === "undefined") {
                            alert("JSZip library is missing.");
                            return;
                        }

                        const zip = new JSZip();

                        zip.file("Attendance.csv",
                            makeCSV(window.HOSPITAL_DATA.attendanceData?.months || [])
                        );

                        zip.file("Staff_Performance.csv",
                            makeCSV(window.HOSPITAL_DATA.staffData || [])
                        );

                        zip.file("Insurance_Claims.csv",
                            makeCSV(window.HOSPITAL_DATA.insuranceData?.months || [])
                        );

                        zip.file("Billing_Report.csv",
                            makeCSV(window.HOSPITAL_DATA.billingData?.months || [])
                        );

                        zip.file("Pharmacy_Sales.csv",
                            makeCSV(window.HOSPITAL_DATA.pharmacyData?.months || [])
                        );

                        zip.file("Patient_Census.csv",
                            makeCSV(window.HOSPITAL_DATA.censusData || [])
                        );

                        zip.file("Payroll_Report.csv",
                            makeCSV(window.HOSPITAL_DATA.payrollData?.months || [])
                        );

                        zip.file("Inventory_Report.csv",
                            makeCSV(window.HOSPITAL_DATA.inventoryData || [])
                        );

                        const blob = await zip.generateAsync({
                            type: "blob"
                        });

                        const link = document.createElement("a");
                        link.href = URL.createObjectURL(blob);
                        link.download = "Hospital_Full_Analytics.zip";
                        link.click();
                    }

                    /* Convert array data → CSV text */
                    function makeCSV(rows) {
                        if (!rows || rows.length === 0) return "";

                        let csv = Object.keys(rows[0]).join(",") + "\n";

                        rows.forEach(r => {
                            csv += Object.values(r).join(",") + "\n";
                        });

                        return csv;
                    }

                    /* ============================================================
   PART 6 — EXPORT ENGINE (CSV, PDF, ZIP)
============================================================ */

                    /* ------------------------------------------
                       EXPORT DASHBOARD AS PDF (html2pdf)
                    ------------------------------------------ */
                    async function exportDashboardPDF() {

                        const chatBtn = document.getElementById("aiChatBtn");
                        if (chatBtn) chatBtn.style.display = "none"; // hide from screenshot

                        const element = document.body;

                        const opt = {
                            margin: 0.4,
                            filename: `Hospital_Dashboard_${DEFAULTS.startDate}_to_${DEFAULTS.endDate}.pdf`,
                            html2canvas: {
                                scale: 2,
                                scrollY: 0
                            },
                            jsPDF: {
                                unit: 'in',
                                format: 'A4',
                                orientation: 'portrait'
                            }
                        };

                        await html2pdf().from(element).set(opt).save();

                        if (chatBtn) chatBtn.style.display = "block";
                    }

                    /* ------------------------------------------
                       GENERIC CSV EXPORTER
                    ------------------------------------------ */
                    function exportCSV(filename, rows) {
                        if (!rows || rows.length === 0) {
                            alert("No data available to export.");
                            return;
                        }

                        let csv = "";

                        // CSV Header
                        csv += Object.keys(rows[0]).join(",") + "\n";

                        // CSV Rows
                        rows.forEach(r => {
                            csv += Object.values(r).join(",") + "\n";
                        });

                        const blob = new Blob([csv], {
                            type: "text/csv"
                        });
                        const link = document.createElement("a");

                        link.href = URL.createObjectURL(blob);
                        link.download = filename;
                        link.click();
                    }

                    /* ------------------------------------------
                       PER-MODULE CSV EXPORT SLOTS
                    ------------------------------------------ */
                    function exportAttendanceCSV() {
                        exportCSV("Attendance_Report.csv", window.HOSPITAL_DATA.attendanceData?.months || []);
                    }

                    function exportStaffCSV() {
                        exportCSV("Staff_Performance.csv", window.HOSPITAL_DATA.staffData || []);
                    }

                    function exportInsuranceCSV() {
                        exportCSV("Insurance_Claims.csv", window.HOSPITAL_DATA.insuranceData?.months || []);
                    }

                    function exportBillingCSV() {
                        exportCSV("Billing_Report.csv", window.HOSPITAL_DATA.billingData?.months || []);
                    }

                    function exportPharmacyCSV() {
                        exportCSV("Pharmacy_Sales.csv", window.HOSPITAL_DATA.pharmacyData?.months || []);
                    }

                    function exportCensusCSV() {
                        exportCSV("Patient_Census.csv", window.HOSPITAL_DATA.censusData || []);
                    }

                    function exportPayrollCSV() {
                        exportCSV("Payroll_Report.csv", window.HOSPITAL_DATA.payrollData?.months || []);
                    }

                    function exportInventoryCSV() {
                        exportCSV("Inventory_Report.csv", window.HOSPITAL_DATA.inventoryData || []);
                    }

                    /* ------------------------------------------
                       ZIP EXPORT — ALL REPORTS IN ONE ZIP FILE
                    ------------------------------------------ */
                    async function exportAllToZip() {

                        if (typeof JSZip === "undefined") {
                            alert("JSZip library is missing.");
                            return;
                        }

                        const zip = new JSZip();

                        zip.file("Attendance.csv",
                            makeCSV(window.HOSPITAL_DATA.attendanceData?.months || [])
                        );

                        zip.file("Staff_Performance.csv",
                            makeCSV(window.HOSPITAL_DATA.staffData || [])
                        );

                        zip.file("Insurance_Claims.csv",
                            makeCSV(window.HOSPITAL_DATA.insuranceData?.months || [])
                        );

                        zip.file("Billing_Report.csv",
                            makeCSV(window.HOSPITAL_DATA.billingData?.months || [])
                        );

                        zip.file("Pharmacy_Sales.csv",
                            makeCSV(window.HOSPITAL_DATA.pharmacyData?.months || [])
                        );

                        zip.file("Patient_Census.csv",
                            makeCSV(window.HOSPITAL_DATA.censusData || [])
                        );

                        zip.file("Payroll_Report.csv",
                            makeCSV(window.HOSPITAL_DATA.payrollData?.months || [])
                        );

                        zip.file("Inventory_Report.csv",
                            makeCSV(window.HOSPITAL_DATA.inventoryData || [])
                        );

                        const blob = await zip.generateAsync({
                            type: "blob"
                        });

                        const link = document.createElement("a");
                        link.href = URL.createObjectURL(blob);
                        link.download = "Hospital_Full_Analytics.zip";
                        link.click();
                    }

                    /* Convert array data → CSV text */
                    function makeCSV(rows) {
                        if (!rows || rows.length === 0) return "";

                        let csv = Object.keys(rows[0]).join(",") + "\n";

                        rows.forEach(r => {
                            csv += Object.values(r).join(",") + "\n";
                        });

                        return csv;
                    }

                    /* ============================================================
   FINANCIAL OVERVIEW SECTION
============================================================ */

                    function renderFinancialSection() {

                        // FIXED monthly values from your system
                        const revenue = 146285.44;
                        const expenses = 475000.00;
                        const profit = revenue - expenses;

                        const statusBox = document.getElementById("financial_status_box");
                        const profitEl = document.getElementById("financial_profit_value");
                        const aiEl = document.getElementById("financial_ai_analysis");

                        /* -------------------------------
                             DETERMINE STATUS
                        --------------------------------*/
                        if (profit >= 0) {
                            statusBox.style.background = "#d4edda";
                            statusBox.style.color = "#155724";
                            statusBox.style.boxShadow = "0 0 18px rgba(0,255,0,0.45)";
                            statusBox.innerHTML = "🟢 PROFITABLE — The hospital is earning money.";

                            profitEl.style.color = "green";
                            profitEl.innerText = "₱" + profit.toLocaleString(undefined, {
                                minimumFractionDigits: 2
                            });

                            aiEl.innerHTML = `
            The hospital is <b>financially profitable</b> this month.
            Revenue is sufficient to cover operational expenses.
            <br><br>
            <b>Strengths:</b><br>
            • Strong billing revenue<br>
            • Good pharmacy income<br>
            • Stable staff cost control
        `;
                        } else {
                            statusBox.style.background = "#f8d7da";
                            statusBox.style.color = "#721c24";
                            statusBox.style.boxShadow = "0 0 18px rgba(255,0,0,0.45)";
                            statusBox.innerHTML = "🔴 LOSING MONEY — Expenses exceed hospital income.";

                            profitEl.style.color = "red";
                            profitEl.innerText = "-₱" + Math.abs(profit).toLocaleString(undefined, {
                                minimumFractionDigits: 2
                            });

                            aiEl.innerHTML = `
            The hospital is currently <b>operating at a loss</b>.
            <br><br>
            <b>Key Cost Drivers:</b><br>
            • High ICU operating cost (₱150,000)<br>
            • Staff salaries (₱308,000)<br>
            • 4 beds under maintenance (₱20,000)<br><br>
            <b>Recommendations:</b><br>
            • Increase ICU + private room utilization<br>
            • Improve billing and collection rate<br>
            • Optimize staff scheduling<br>
        `;
                        }

                        /* -------------------------------
                             CHART 1 — REVENUE VS EXPENSES
                        --------------------------------*/
                        new Chart(document.getElementById("chart_fin_rev_exp"), {
                            type: "bar",
                            data: {
                                labels: ["Revenue", "Expenses", "Net Profit"],
                                datasets: [{
                                    label: "Amount (PHP)",
                                    data: [revenue, expenses, profit],
                                    backgroundColor: ["#28a745", "#dc3545", "#007bff"]
                                }]
                            }
                        });

                        /* -------------------------------
                             CHART 2 — EXPENSE BREAKDOWN
                        --------------------------------*/
                        new Chart(document.getElementById("chart_fin_breakdown"), {
                            type: "pie",
                            data: {
                                labels: [
                                    "Staff", "Utilities", "Medical Supplies",
                                    "Maintenance", "Admin", "Depreciation"
                                ],
                                datasets: [{
                                    data: [308000, 30000, 17000, 20000, 40000, 60000],
                                    backgroundColor: [
                                        "#ff6384", "#36a2eb", "#ffcd56",
                                        "#4bc0c0", "#9966ff", "#ff9f40"
                                    ]
                                }]
                            }
                        });

                        /* -------------------------------
                             CHART 3 — TREND LINE
                        --------------------------------*/
                        new Chart(document.getElementById("chart_fin_trend"), {
                            type: "line",
                            data: {
                                labels: ["Last Month", "This Month", "Projected Next"],
                                datasets: [{
                                    label: "Trend",
                                    borderColor: "#6610f2",
                                    data: [180000, revenue, revenue * 0.92],
                                    fill: false
                                }]
                            }
                        });
                    }
                </script>
            </div>
        </div>
    </div>
</body>

</html>