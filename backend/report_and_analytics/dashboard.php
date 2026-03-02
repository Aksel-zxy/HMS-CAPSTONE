<?php include 'header.php'; ?>
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

        /* MAIN GRID CARD STYLE */
        .dashboard-section {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e3e7ee;
            padding: 20px;
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 18px;
            color: #0d6efd;
        }

        .chart-container {
            width: 100%;
            height: 350px;
        }

        /* GRID LAYOUT (2 columns desktop) */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .grid-1 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        /* FLOATING AI BUTTON */
        #aiFloatingBtn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            font-size: 28px;
        }

        /* SLIDE-IN AI PANEL */
        #aiSlidePanel {
            position: fixed;
            top: 0;
            right: -360px;
            width: 360px;
            height: 100vh;
            background: #ffffff;
            border-left: 1px solid #dce3eb;
            z-index: 9998;
            padding: 20px;
            overflow-y: auto;
            transition: right 0.35s ease-in-out;
        }

        #aiSlidePanel.active {
            right: 0px;
        }

        #closeAISide {
            font-size: 22px;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 12px;
        }

        /* --- Fade-in animation --- */
        .fade-in {
            opacity: 0;
            transform: translateY(8px);
            animation: fadeIn .6s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0px);
            }
        }

        /* --- Shimmer loading skeleton --- */
        .skeleton {
            background: linear-gradient(90deg,
                    #e9eef3 0%,
                    #f7f9fb 50%,
                    #e9eef3 100%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite linear;
            border-radius: 6px;
        }

        @keyframes shimmer {
            0% {
                background-position: -180px 0;
            }

            100% {
                background-position: 180px 0;
            }
        }

        .skel-card {
            height: 120px;
            margin-bottom: 15px;
        }

        .skel-chart {
            height: 260px;
            margin-bottom: 15px;
        }

        /* --- Full-screen loading overlay --- */
        #dashboardLoader {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
        }

        #dashboardLoader.active {
            opacity: 1;
            pointer-events: all;
        }

        .loader-spinner {
            width: 55px;
            height: 55px;
            border: 5px solid #d1d9e6;
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: spin 1s infinite linear;
        }

        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

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
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
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
            transition: all .25s ease;
        }

        #aiChatWindow.hidden {
            transform: translateY(15px);
            opacity: 0;
            pointer-events: none;
        }

        .ai-chat-header {
            background: #0d6efd;
            color: white;
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-size: .9rem;
            max-width: 85%;
        }

        .ai-msg.user {
            background: #d1eaff;
            align-self: flex-end;
        }

        .ai-msg.bot {
            background: white;
            border: 1px solid #e4e6eb;
            align-self: flex-start;
        }

        .ai-chat-input {
            display: flex;
            border-top: 1px solid #dce3eb;
            padding: 8px;
            background: white;
        }

        .ai-chat-input input {
            flex: 1;
            border: 1px solid #ccc;
            padding: 7px;
            border-radius: 5px;
        }

        .ai-chat-input button {
            margin-left: 8px;
            background: #0d6efd;
            border: none;
            color: white;
            padding: 7px 12px;
            border-radius: 5px;
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <!-- AI CHAT WIDGET -->
        <div id="aiChatWidget">

            <!-- Floating Button -->
            <button id="aiChatBtn" onclick="toggleAIChat()">💬 AI Assistant</button>

            <!-- Chat Window -->
            <div id="aiChatWindow" class="hidden">
                <div class="ai-chat-header">
                    <strong>AI Hospital Assistant</strong>
                    <button onclick="toggleAIChat()">✖</button>
                </div>

                <div id="aiChatMessages" class="ai-chat-messages"></div>

                <div class="ai-chat-input">
                    <input id="aiChatInput" type="text" placeholder="Ask anything…"
                        onkeypress="if(event.key==='Enter') sendAIMessage()">
                    <button onclick="sendAIMessage()">Send</button>
                </div>
            </div>

        </div>
        <?php include 'sidebar.php'; ?>

        <div class="page-container">

            <!-- DASHBOARD LOADING OVERLAY -->
            <div id="dashboardLoader">
                <div class="loader-spinner"></div>
                <div class="mt-3 fw-bold text-secondary">Loading dashboard…</div>
            </div>

            <h1 class="fw-bold mb-4">🏥 Hospital Analytics Dashboard</h1>

            <div class="d-flex justify-content-end mb-4 gap-2">
                <button class="btn btn-danger" onclick="exportDashboardPDF()">
                    📄 Export Dashboard PDF
                </button>

                <button class="btn btn-secondary" onclick="exportAllToZip()">
                    🗂 Export ALL (ZIP)
                </button>
            </div>
            <!-- ===================== OVERVIEW SECTION ======================== -->
            <div id="overview_section" class="dashboard-section">
                <div class="section-title">📌 Hospital Overview</div>
                <div id="overview_kpis" class="row g-3">
                    <!-- KPIs will be injected here in Part 12 -->
                </div>
            </div>

            <!-- GRID LAYOUT BEGINS -->
            <div class="grid-2">

                <!-- ===================== ATTENDANCE ======================== -->
                <div id="attendance_section" class="dashboard-section">
                    <div class="section-title d-flex justify-content-between">
                        <span>🟦 Employee Attendance</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportAttendanceCSV()">Export CSV</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                    <div id="attendance_stats" class="mt-3"></div>
                </div>

                <!-- ===================== STAFF PERFORMANCE ======================== -->
                <div id="staff_section" class="dashboard-section">
                    <div class="section-title d-flex justify-content-between">
                        <span>🟩 Staff Performance</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportStaffCSV()">Export CSV</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="staffAvgChart"></canvas>
                    </div>
                    <div id="staff_stats" class="mt-3"></div>
                </div>

                <!-- ===================== INSURANCE CLAIMS ======================== -->
                <div id="insurance_section" class="dashboard-section">
                    <div class="section-title d-flex justify-content-between">
                        <span>🟧 Insurance Claims</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportInsuranceCSV()">Export CSV</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="insuranceChart"></canvas>
                    </div>
                    <div id="insurance_stats" class="mt-3"></div>
                </div>

                <!-- ===================== BILLING ======================== -->
                <div id="billing_section" class="dashboard-section">
                    <div class="section-title d-flex justify-content-between">
                        <span>💰 Billing Summary</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportBillingCSV()">Export CSV</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="billingChart"></canvas>
                    </div>
                    <div id="billing_stats" class="mt-3"></div>
                </div>

                <!-- ===================== PHARMACY ======================== -->
                <div id="pharmacy_section" class="dashboard-section">
                    <div class="section-title d-flex justify-content-between">
                        <span>💊 Pharmacy Sales</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportPharmacyCSV()">Export CSV</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="pharmacyChart"></canvas>
                    </div>
                    <div id="pharmacy_stats" class="mt-3"></div>
                </div>

                <!-- ===================== PATIENT CENSUS ======================== -->
                <div id="census_section" class="dashboard-section">
                    <div class="section-title d-flex justify-content-between">
                        <span>🛏 Patient Census</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportCensusCSV()">Export CSV</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="censusChart"></canvas>
                    </div>
                    <div id="census_stats" class="mt-3"></div>
                </div>

                <!-- ===================== PAYROLL ======================== -->
                <div id="payroll_section" class="dashboard-section">
                    <div class="section-title d-flex justify-content-between">
                        <span>📄 Payroll Summary</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportPayrollCSV()">Export CSV</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="payrollChart"></canvas>
                    </div>
                    <div id="payroll_stats" class="mt-3"></div>
                </div>

                <!-- ===================== INVENTORY ======================== -->
                <div id="inventory_section" class="dashboard-section">
                    <div class="section-title d-flex justify-content-between">
                        <span>📦 Inventory Status</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportInventoryCSV()">Export CSV</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="inventoryChart"></canvas>
                    </div>
                    <div id="inventory_stats" class="mt-3"></div>
                </div>

            </div> <!-- END GRID -->

        </div>
    </div>

    <!-- FLOATING INSIGHT BUTTON -->
    <button id="aiFloatingBtn" onclick="toggleAI()">💡</button>

    <!-- SLIDE-IN PANEL -->
    <div id="aiSlidePanel">
        <span id="closeAISide" onclick="toggleAI()">✖</span>

        <h5 class="fw-bold mb-3">AI Insights</h5>
        <div id="ai_insights"></div>

        <hr>
        <h5 class="fw-bold mb-3">AI Recommendations</h5>
        <div id="ai_reco"></div>

        <hr>
        <h5 class="fw-bold mb-3">Hospital Risk Assessment</h5>
        <div id="ai_risk"></div>

    </div>

    <script>
        function toggleAI() {
            document.getElementById("aiSlidePanel").classList.toggle("active");
        }
        /////////////////////////////////////////////////////
        // 1. DEFAULT RANGE (2025 → Feb 2026)
        /////////////////////////////////////////////////////

        const DEFAULTS = {
            startDate: "2025-01-01",
            endDate: "2026-02-28",

            startMonth: 1,
            startYear: 2025,
            endMonth: 2,
            endYear: 2026
        };


        /////////////////////////////////////////////////////
        // 2. API ENDPOINT BUILDERS
        /////////////////////////////////////////////////////

        const API = {
            attendance: () => `https://localhost:7212/employee/monthAttendanceRangeQueryReport?start=${DEFAULTS.startMonth}&startYear=${DEFAULTS.startYear}&endMonth=${DEFAULTS.endMonth}&endYear=${DEFAULTS.endYear}`,

            staffPerformance: () => `https://localhost:7212/employee/staffPerformanceAndAttendanceReport?start=${DEFAULTS.startDate}&end=${DEFAULTS.endDate}`,

            insurance: () => `https://localhost:7212/insurance/monthInsuranceClaimRangeQuery?start=${DEFAULTS.startDate}&end=${DEFAULTS.endDate}`,

            billing: () => `https://localhost:7212/journal/monthBillingRangeReport?start=${DEFAULTS.startMonth}&startYear=${DEFAULTS.startYear}&endMonth=${DEFAULTS.endMonth}&endYear=${DEFAULTS.endYear}`,

            pharmacy: () => `https://localhost:7212/journal/monthPharmacyRangeReport?start=${DEFAULTS.startMonth}&startYear=${DEFAULTS.startYear}&endMonth=${DEFAULTS.endMonth}&endYear=${DEFAULTS.endYear}`,

            census: () => `https://localhost:7212/patient/getCensus?startDate=${DEFAULTS.startDate}&endDate=${DEFAULTS.endDate}`,

            payroll: () => `https://localhost:7212/payroll/monthPayrollRangeQueryAsync?startmonth=${DEFAULTS.startMonth}&startyear=${DEFAULTS.startYear}&endmonth=${DEFAULTS.endMonth}&endyear=${DEFAULTS.endYear}`,

            inventory: () => `https://localhost:7212/property/inventoryReport`
        };


        /////////////////////////////////////////////////////
        // 3. FETCH WRAPPER (safe)
        /////////////////////////////////////////////////////

        async function fetchJSON(url, label) {
            try {
                const res = await fetch(url);
                if (!res.ok) throw new Error(`${label} endpoint error`);
                return await res.json();
            } catch (err) {
                console.error("❌ API ERROR —", label, err);
                return null;
            }
        }


        /////////////////////////////////////////////////////
        // 4. LOAD ALL 8 MODULES IN PARALLEL
        /////////////////////////////////////////////////////

        async function loadDashboard() {
            console.log("📡 Loading all hospital analytics…");

            // Show skeletons
            showLoadingStates();

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
                fetchJSON(API.insurance(), "Insurance"),
                fetchJSON(API.billing(), "Billing"),
                fetchJSON(API.pharmacy(), "Pharmacy"),
                fetchJSON(API.census(), "Census"),
                fetchJSON(API.payroll(), "Payroll"),
                fetchJSON(API.inventory(), "Inventory")

            ]);

            // Pass to part 3 (chart rendering)
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

            renderAllSections();
            generateAIInsights();
        }



        /////////////////////////////////////////////////////
        // 5. LOADING PLACEHOLDERS
        /////////////////////////////////////////////////////

        function showLoadingStates() {
            const placeholders = `
        <div class="col-12 text-center text-muted py-4">
            <div class="spinner-border text-primary"></div><br>
            <small>Loading…</small>
        </div>
    `;

            document.getElementById("overview_kpis").innerHTML = placeholders;
            document.getElementById("attendance_stats").innerHTML = placeholders;
            document.getElementById("staff_stats").innerHTML = placeholders;
            document.getElementById("insurance_stats").innerHTML = placeholders;
            document.getElementById("billing_stats").innerHTML = placeholders;
            document.getElementById("pharmacy_stats").innerHTML = placeholders;
            document.getElementById("census_stats").innerHTML = placeholders;
            document.getElementById("payroll_stats").innerHTML = placeholders;
            document.getElementById("inventory_stats").innerHTML = placeholders;
        }



        /////////////////////////////////////////////////////
        // 6. RENDER SECTION WRAPPER (charts + summaries)
        /////////////////////////////////////////////////////

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



        /////////////////////////////////////////////////////
        // 7. AUTO-LOAD ON PAGE START
        /////////////////////////////////////////////////////

        window.onload = () => {
            loadDashboard();
        };

        //////////////////////////////////////////////////////////
        // CHART REGISTRY  (so we can destroy charts before redraw)
        //////////////////////////////////////////////////////////
        let charts = {};

        function resetChart(id) {
            if (charts[id]) charts[id].destroy();
        }


        //////////////////////////////////////////////////////////
        // 1. OVERVIEW KPIs
        //////////////////////////////////////////////////////////
        function renderOverview() {
            const d = window.HOSPITAL_DATA;

            const totalAttendance = d.attendanceData?.present ?? 0;
            const totalClaims = d.insuranceData?.total_claims ?? 0;
            const totalBilling = d.billingData?.total_billed ?? 0;
            const totalSales = d.pharmacyData?.totalSales ?? 0;
            const totalPayroll = d.payrollData?.total_gross_pay ?? 0;
            const totalInventory = d.inventoryData?.length ?? 0;

            document.getElementById("overview_kpis").innerHTML = `
        <div class="col-md-2">
            <div class="p-3 text-center bg-white border rounded">
                <h6 class="text-muted">Attendance</h6>
                <h3>${totalAttendance}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="p-3 text-center bg-white border rounded">
                <h6 class="text-muted">Insurance Claims</h6>
                <h3>${totalClaims}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="p-3 text-center bg-white border rounded">
                <h6 class="text-muted">Billing</h6>
                <h3>₱${totalBilling.toLocaleString()}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="p-3 text-center bg-white border rounded">
                <h6 class="text-muted">Pharmacy</h6>
                <h3>₱${totalSales.toLocaleString()}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="p-3 text-center bg-white border rounded">
                <h6 class="text-muted">Payroll</h6>
                <h3>₱${totalPayroll.toLocaleString()}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="p-3 text-center bg-white border rounded">
                <h6 class="text-muted">Inventory Items</h6>
                <h3>${totalInventory}</h3>
            </div>
        </div>
    `;
        }



        //////////////////////////////////////////////////////////
        // 2. ATTENDANCE CHART
        //////////////////////////////////////////////////////////
        function renderAttendance() {
            const data = window.HOSPITAL_DATA.attendanceData;

            if (!data || !data.months) return;

            const labels = data.months.map(m => `Month ${m.month}`);
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



        //////////////////////////////////////////////////////////
        // 3. STAFF PERFORMANCE CHART
        //////////////////////////////////////////////////////////
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
                        label: "Avg Evaluation Score",
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



        //////////////////////////////////////////////////////////
        // 4. INSURANCE CLAIMS CHART
        //////////////////////////////////////////////////////////
        function renderInsurance() {
            const data = window.HOSPITAL_DATA.insuranceData;
            if (!data || !data.months) return;

            const labels = data.months.map(m => `Month ${m.month}`);
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
        <b>Denied:</b> ${data.total_denied_claims}<br>
    `;
        }



        //////////////////////////////////////////////////////////
        // 5. BILLING CHART
        //////////////////////////////////////////////////////////
        function renderBilling() {
            const data = window.HOSPITAL_DATA.billingData;
            if (!data || !data.months) return;

            const labels = data.months.map(m => `Month ${m.month}`);
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



        //////////////////////////////////////////////////////////
        // 6. PHARMACY SALES CHART
        //////////////////////////////////////////////////////////
        function renderPharmacy() {
            const data = window.HOSPITAL_DATA.pharmacyData;
            if (!data || !data.months) return;

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
        <b>Top Selling Item:</b> ${data.topSellingItem}
    `;
        }



        //////////////////////////////////////////////////////////
        // 7. PATIENT CENSUS CHART
        //////////////////////////////////////////////////////////
        function renderCensus() {
            const data = window.HOSPITAL_DATA.censusData;
            if (!Array.isArray(data)) return;

            const labels = data.map(p => p.condition_name || "N/A");
            const countByCondition = {};

            labels.forEach(l => {
                countByCondition[l] = (countByCondition[l] || 0) + 1
            });

            resetChart("censusChart");

            charts["censusChart"] = new Chart(censusChart, {
                type: "doughnut",
                data: {
                    labels: Object.keys(countByCondition),
                    datasets: [{
                        data: Object.values(countByCondition),
                        backgroundColor: ["#0d6efd", "#6f42c1", "#20c997", "#ffc107", "#dc3545"]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            document.getElementById("census_stats").innerHTML = `
        <b>Total Assigned Patients:</b> ${data.length}
    `;
        }



        //////////////////////////////////////////////////////////
        // 8. PAYROLL CHART
        //////////////////////////////////////////////////////////
        function renderPayroll() {
            const data = window.HOSPITAL_DATA.payrollData;
            if (!data || !data.months) return;

            const labels = data.months.map(m => `M${m.month}`);
            const net = data.months.map(m => m.total_net_pay);

            resetChart("payrollChart");

            charts["payrollChart"] = new Chart(payrollChart, {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                        label: "Net Pay",
                        data: net,
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



        //////////////////////////////////////////////////////////
        // 9. INVENTORY CHART
        //////////////////////////////////////////////////////////
        function renderInventory() {
            const data = window.HOSPITAL_DATA.inventoryData;
            if (!data) return;

            const labels = data.map(i => i.item_name);
            const qty = data.map(i => i.quantity);

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

            document.getElementById("inventory_stats").innerHTML = `
        <b>Total Inventory Items:</b> ${data.length}<br>
        <b>Low Stock Items:</b> ${data.filter(x => x.quantity <= x.min_stock).length}
    `;
        }

        //////////////////////////////////////////////////////////
        // PART 4 — AI INSIGHT + RECOMMENDATION ENGINE
        //////////////////////////////////////////////////////////

        function generateAIInsights() {
            const D = window.HOSPITAL_DATA;
            if (!D) return;

            let insights = [];
            let reco = [];
            let riskScore = 0;

            //////////////////////////////////////////////////////////
            // 1. STAFF PERFORMANCE ANALYSIS
            //////////////////////////////////////////////////////////
            if (D.staffData) {
                const top = D.staffData.reduce((a, b) =>
                    a.departmentEvaluationAverageScore > b.departmentEvaluationAverageScore ? a : b
                );

                const low = D.staffData.reduce((a, b) =>
                    a.departmentEvaluationAverageScore < b.departmentEvaluationAverageScore ? a : b
                );

                insights.push(`Top performing department: <b>${top.department}</b> (Score ${top.departmentEvaluationAverageScore.toFixed(2)})`);
                insights.push(`Lowest performing department: <b>${low.department}</b> (Score ${low.departmentEvaluationAverageScore.toFixed(2)})`);

                // Risk: too many late employees
                const totalLate = D.staffData.reduce((a, b) => a + b.deparmentTotalLateEmployee, 0);
                if (totalLate > 20) {
                    riskScore += 1;
                    reco.push("High lateness detected — review staff scheduling and attendance.");
                }
            }


            //////////////////////////////////////////////////////////
            // 2. ATTENDANCE TRENDS
            //////////////////////////////////////////////////////////
            if (D.attendanceData?.months) {
                const m = D.attendanceData.months;
                const lateSum = m.reduce((a, b) => a + b.late, 0);
                const absentSum = m.reduce((a, b) => a + b.absent, 0);

                insights.push(`Total Lates Recorded: <b>${lateSum}</b>`);
                insights.push(`Total Absences: <b>${absentSum}</b>`);

                if (absentSum > 10) {
                    riskScore += 1;
                    reco.push("Absenteeism exceeds normal levels — investigate possible causes.");
                }
            }


            //////////////////////////////////////////////////////////
            // 3. INSURANCE CLAIMS
            //////////////////////////////////////////////////////////
            if (D.insuranceData) {
                const approved = D.insuranceData.total_approved_claims;
                const denied = D.insuranceData.total_denied_claims;

                insights.push(`Insurance approval rate: <b>${approved}</b> approved vs <b>${denied}</b> denied`);

                if (denied > approved) {
                    riskScore += 1;
                    reco.push("High denial rate — verify documentation and provider compliance.");
                }
            }


            //////////////////////////////////////////////////////////
            // 4. BILLING HEALTH
            //////////////////////////////////////////////////////////
            if (D.billingData) {
                const pending = D.billingData.total_pending_amount;

                insights.push(`Pending Billing Amount: <b>₱${pending.toLocaleString()}</b>`);

                if (pending > 50000) {
                    riskScore += 1;
                    reco.push("Pending billing is high — follow up with insurance and patients.");
                }
            }


            //////////////////////////////////////////////////////////
            // 5. PHARMACY SALES PERFORMANCE
            //////////////////////////////////////////////////////////
            if (D.pharmacyData) {
                insights.push(`Top Selling Medicine: <b>${D.pharmacyData.topSellingItem}</b>`);
                const drop = D.pharmacyData.months.find(m => m.totalSales < 1000);
                if (drop) {
                    riskScore += 1;
                    reco.push(`Very low pharmacy sales detected in month ${drop.month} — check inventory and POS logs.`);
                }
            }


            //////////////////////////////////////////////////////////
            // 6. PATIENT CENSUS
            //////////////////////////////////////////////////////////
            if (Array.isArray(D.censusData)) {
                const totalPatients = D.censusData.length;

                insights.push(`Total active patients recorded: <b>${totalPatients}</b>`);

                const highBeds = D.censusData.filter(x => x.released_date === null).length;
                if (highBeds > 20) {
                    riskScore += 1;
                    reco.push("High active bed usage — monitor bed turnover closely.");
                }
            }


            //////////////////////////////////////////////////////////
            // 7. PAYROLL
            //////////////////////////////////////////////////////////
            if (D.payrollData) {
                const gross = D.payrollData.total_gross_pay;
                insights.push(`Total Payroll Gross Pay: <b>₱${gross.toLocaleString()}</b>`);

                if (gross > 5000000) {
                    riskScore += 1;
                    reco.push("Payroll expenses are high — review staffing allocation.");
                }
            }


            //////////////////////////////////////////////////////////
            // 8. INVENTORY HEALTH
            //////////////////////////////////////////////////////////
            if (D.inventoryData) {
                const lowStock = D.inventoryData.filter(i => i.quantity <= i.min_stock).length;

                insights.push(`Low stock items: <b>${lowStock}</b>`);

                if (lowStock > 3) {
                    riskScore += 1;
                    reco.push("Supply shortage detected — restock immediately.");
                }
            }


            //////////////////////////////////////////////////////////
            // FINAL RISK SCORE
            //////////////////////////////////////////////////////////

            let riskText = "";
            if (riskScore === 0) riskText = "🟢 LOW RISK — Hospital is performing stably.";
            else if (riskScore === 1) riskText = "🟡 MODERATE RISK — Some areas require monitoring.";
            else if (riskScore >= 2) riskText = "🔴 HIGH RISK — Multiple issues detected. Immediate action recommended.";


            //////////////////////////////////////////////////////////
            // OUTPUT TO UI PANEL
            //////////////////////////////////////////////////////////

            document.getElementById("ai_insights").innerHTML =
                insights.map(x => `• ${x}<br>`).join("");

            document.getElementById("ai_reco").innerHTML =
                reco.length ? reco.map(x => `• ${x}<br>`).join("") : "No recommendations — system stable.";

            document.getElementById("ai_risk").innerHTML = `<b>${riskText}</b>`;
        }

        //////////////////////////////////////////////////////////
        // PART 5 — EXPORT ENGINE (PDF + Excel)
        //////////////////////////////////////////////////////////

        //////////////////////////////////////////////////////////
        // 1. EXPORT WHOLE DASHBOARD TO PDF
        //////////////////////////////////////////////////////////

        async function exportDashboardPDF() {

            // Hide floating button before export
            document.getElementById("aiFloatingBtn").style.display = "none";

            const element = document.body;

            const opt = {
                margin: 0.5,
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

            // Show floating button again
            document.getElementById("aiFloatingBtn").style.display = "block";
        }


        //////////////////////////////////////////////////////////
        // 2. EXPORT INDIVIDUAL MODULES TO CSV
        //////////////////////////////////////////////////////////
        function exportCSV(filename, rows) {
            let csv = "";

            // Header
            csv += Object.keys(rows[0]).join(",") + "\n";

            // Rows
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


        //////////////////////////////////////////////////////////
        // 3. EXPORTERS PER MODULE
        //////////////////////////////////////////////////////////

        function exportAttendanceCSV() {
            const d = window.HOSPITAL_DATA.attendanceData;
            if (!d) return;

            exportCSV("Attendance_Report.csv", d.months);
        }

        function exportStaffCSV() {
            const d = window.HOSPITAL_DATA.staffData;
            if (!d) return;

            exportCSV("Staff_Performance.csv", d);
        }

        function exportInsuranceCSV() {
            const d = window.HOSPITAL_DATA.insuranceData;
            if (!d) return;

            exportCSV("Insurance_Claims.csv", d.months);
        }

        function exportBillingCSV() {
            const d = window.HOSPITAL_DATA.billingData;
            if (!d) return;

            exportCSV("Billing_Report.csv", d.months);
        }

        function exportPharmacyCSV() {
            const d = window.HOSPITAL_DATA.pharmacyData;
            if (!d) return;

            exportCSV("Pharmacy_Sales.csv", d.months);
        }

        function exportCensusCSV() {
            const d = window.HOSPITAL_DATA.censusData;
            if (!d) return;

            exportCSV("Patient_Census.csv", d);
        }

        function exportPayrollCSV() {
            const d = window.HOSPITAL_DATA.payrollData;
            if (!d) return;

            exportCSV("Payroll_Report.csv", d.months);
        }

        function exportInventoryCSV() {
            const d = window.HOSPITAL_DATA.inventoryData;
            if (!d) return;

            exportCSV("Inventory_Report.csv", d);
        }


        //////////////////////////////////////////////////////////
        // 4. OPTIONAL — EXPORT ALL INTO ONE ZIP FILE (Part 5B)
        //////////////////////////////////////////////////////////

        async function exportAllToZip() {
            const zip = new JSZip();

            zip.file("Attendance.csv", makeCSV(window.HOSPITAL_DATA.attendanceData?.months || []));
            zip.file("Staff.csv", makeCSV(window.HOSPITAL_DATA.staffData || []));
            zip.file("Insurance.csv", makeCSV(window.HOSPITAL_DATA.insuranceData?.months || []));
            zip.file("Billing.csv", makeCSV(window.HOSPITAL_DATA.billingData?.months || []));
            zip.file("Pharmacy.csv", makeCSV(window.HOSPITAL_DATA.pharmacyData?.months || []));
            zip.file("Census.csv", makeCSV(window.HOSPITAL_DATA.censusData || []));
            zip.file("Payroll.csv", makeCSV(window.HOSPITAL_DATA.payrollData?.months || []));
            zip.file("Inventory.csv", makeCSV(window.HOSPITAL_DATA.inventoryData || []));

            const blob = await zip.generateAsync({
                type: "blob"
            });

            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "Hospital_Full_Analytics.zip";
            link.click();
        }

        function makeCSV(rows) {
            if (rows.length === 0) return "";
            let csv = Object.keys(rows[0]).join(",") + "\n";
            rows.forEach(r => csv += Object.values(r).join(",") + "\n");
            return csv;
        }

        // Show overlay + skeletons
        function startDashboardLoading() {
            document.getElementById("dashboardLoader").classList.add("active");

            // Replace every section with skeletons
            const skel = `
        <div class="skeleton skel-card"></div>
        <div class="skeleton skel-card"></div>
        <div class="skeleton skel-chart"></div>
    `;

            document.querySelectorAll(".section-content")
                .forEach(sec => sec.innerHTML = skel);
        }

        // Hide overlay
        function finishDashboardLoading() {
            setTimeout(() => {
                document.getElementById("dashboardLoader").classList.remove("active");

                // Add fade-in animation to real content
                document.querySelectorAll(".section-content")
                    .forEach(sec => sec.classList.add("fade-in"));
            }, 300);
        }


        // OVERRIDE loadDashboard()
        const oldLoadDashboard = loadDashboard;

        loadDashboard = async function() {
            startDashboardLoading(); // Start animations

            await oldLoadDashboard(); // Run real dashboard load

            finishDashboardLoading(); // Smooth fade-in
        };

        function toggleAIChat() {
            const win = document.getElementById("aiChatWindow");
            win.classList.toggle("hidden");
        }

        function sendAIMessage() {
            const input = document.getElementById("aiChatInput");
            const msg = input.value.trim();
            if (!msg) return;

            // append user message
            appendChatBubble(msg, "user");

            input.value = "";
            aiRespond(msg);
        }

        function appendChatBubble(text, sender) {
            const msgBox = document.getElementById("aiChatMessages");

            const bubble = document.createElement("div");
            bubble.className = "ai-msg " + sender;
            bubble.innerHTML = text;

            msgBox.appendChild(bubble);
            msgBox.scrollTop = msgBox.scrollHeight;
        }

        /////////////////////////////////////////////////////
        // AI RESPONSE ENGINE (USES DASHBOARD DATA)
        /////////////////////////////////////////////////////

        function aiRespond(userMsg) {

            appendChatBubble("Analyzing hospital data…", "bot");

            setTimeout(() => {
                const D = window.HOSPITAL_DATA;

                let answer = "";

                //////////////////////////////////////////////////////
                // EXAMPLE SMART RESPONSES
                //////////////////////////////////////////////////////

                if (userMsg.toLowerCase().includes("attendance")) {
                    const totalLate = D.attendanceData?.months.reduce((a, b) => a + b.late, 0);
                    const totalPres = D.attendanceData?.months.reduce((a, b) => a + b.present, 0);

                    answer = `
                Attendance Summary:<br><br>
                • Total Present: <b>${totalPres}</b><br>
                • Total Late: <b>${totalLate}</b><br>
                • Trend looks ${(totalLate>10)?"concerning 🔴":"stable 🟢"}
            `;
                } else if (userMsg.toLowerCase().includes("insurance")) {
                    answer = `
                Insurance Summary:<br><br>
                • Approved: <b>${D.insuranceData.total_approved_claims}</b><br>
                • Denied: <b>${D.insuranceData.total_denied_claims}</b><br>
                • Approval Rate: <b>${(
                    (D.insuranceData.total_approved_claims /
                     D.insuranceData.total_claims) * 100
                ).toFixed(1)}%</b>
            `;
                } else if (userMsg.toLowerCase().includes("billing")) {
                    answer = `
                Billing Overview:<br><br>
                • Total Billed: ₱${D.billingData.total_billed.toLocaleString()}<br>
                • Total Paid: ₱${D.billingData.total_paid.toLocaleString()}<br>
                • Pending: ₱${D.billingData.total_pending_amount.toLocaleString()}<br>
            `;
                } else if (userMsg.toLowerCase().includes("pharmacy")) {
                    answer = `
                Pharmacy Report:<br><br>
                • Total Sales: ₱${D.pharmacyData.totalSales.toLocaleString()}<br>
                • Top Item: <b>${D.pharmacyData.topSellingItem}</b><br>
            `;
                } else if (userMsg.toLowerCase().includes("census")) {
                    answer = `
                Patient Census:<br><br>
                • Total Patients Recorded: <b>${D.censusData.length}</b><br>
                • Active Beds: <b>${
                    D.censusData.filter(x=>x.released_date===null).length
                }</b>
            `;
                } else if (userMsg.toLowerCase().includes("payroll")) {
                    answer = `
                Payroll Summary:<br><br>
                • Gross Pay: ₱${D.payrollData.total_gross_pay.toLocaleString()}<br>
                • Net Pay: ₱${D.payrollData.total_net_pay.toLocaleString()}<br>
            `;
                } else if (userMsg.toLowerCase().includes("inventory")) {
                    const low = D.inventoryData.filter(i => i.quantity <= i.min_stock).length;
                    answer = `
                Inventory Status:<br><br>
                • Items Monitored: <b>${D.inventoryData.length}</b><br>
                • Low Stock Items: <b>${low}</b>
            `;
                } else {
                    answer = `
                I can help explain any part of the hospital dashboard.<br><br>
                Try asking about:<br>
                • Attendance<br>
                • Staff Performance<br>
                • Insurance Claims<br>
                • Billing<br>
                • Pharmacy<br>
                • Patient Census<br>
                • Payroll<br>
                • Inventory
            `;
                }

                appendChatBubble(answer, "bot");

            }, 600);
        }
    </script>
</body>

</html>