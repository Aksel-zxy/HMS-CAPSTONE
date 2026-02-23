<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Hospital Duty & Appointment Insights</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        #chartWrapper {
            height: 300px;
            width: 100%;
        }

        #insightBox ul {
            padding-left: 20px;
        }

        #insightBox li {
            margin-bottom: 6px;
        }
    </style>
</head>

<body class="bg-light">
    <div class="d-flex">

        <!-- SIDEBAR (UNCHANGED) -->
        <!-- (Keeping your exact sidebar code so layout remains untouched) -->

        <?php
        include 'sidebar.php'
        ?>

        <div class="container my-5">
            <h2 class="text-center mb-4">Duty & Appointment Monitoring</h2>

            <!-- FILTERS -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label">Select Month</label>
                    <select class="form-select" id="selectMonth">
                        <option value="" disabled selected>Choose Month</option>
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Select Year</label>
                    <select class="form-select" id="selectYear">
                        <option value="" disabled selected>Choose Year</option>
                        <option value="2025">2025</option>
                        <option value="2024">2024</option>
                        <option value="2023">2023</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="loadStats()">Filter Reports</button>
                </div>
            </div>

            <!-- STATS CONTAINER -->
            <div id="statsContainer" class="d-none">

                <!-- KPIS -->
                <div class="row text-center mb-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 bg-primary text-white">
                            <div class="card-body">
                                <h5>Total Appointments</h5>
                                <h3 id="totalAppointments">0</h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 bg-success text-white">
                            <div class="card-body">
                                <h5>Doctor Duties</h5>
                                <h3 id="totalDoctorDuties">0</h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 bg-info text-white">
                            <div class="card-body">
                                <h5>Nurse Duties</h5>
                                <h3 id="totalNurseDuties">0</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CHART + TABLE -->
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-primary text-white">Appointment Status Distribution</div>
                            <div class="card-body">
                                <div id="chartWrapper"><canvas id="appointmentChart"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-light">
                                <h5>Month Appointments</h5>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Doctor</th>
                                            <th>Bed</th>
                                            <th>Nurse Assistant</th>
                                            <th>Procedure</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="appointmentsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ INSIGHTS CARD -->
                    <div class="col-lg-12">
                        <div class="card shadow-sm border-0 mt-4">
                            <div class="card-header bg-light">
                                <h5>Insights</h5>
                            </div>
                            <div class="card-body">
                                <div id="insightBox"><em>No insights yet...</em></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- SCRIPT -->
            <script>
                function getStatusBadge(status) {
                    switch (status.toLowerCase()) {
                        case 'completed':
                            return 'bg-success';
                        case 'pending':
                            return 'bg-warning text-dark';
                        case 'cancelled':
                            return 'bg-danger';
                        default:
                            return 'bg-secondary';
                    }
                }

                function initChart(completed, pending, cancelled) {
                    const ctx = document.getElementById('appointmentChart').getContext('2d');

                    if (window.myAppointmentChart) window.myAppointmentChart.destroy();

                    window.myAppointmentChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Completed', 'Pending', 'Cancelled'],
                            datasets: [{
                                data: [completed, pending, cancelled],
                                backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }

                /* ✅ NEW — INSIGHT GENERATION */
                function generateInsights(data, pendingCount) {
                    let insights = [];

                    const completed = data.completed || 0;
                    const cancelled = data.cancelled || 0;
                    const total = data.totalAppointments || 0;

                    // Determine dominant category
                    const dominant = Math.max(completed, cancelled, pendingCount);

                    // 1️⃣ PRIORITY: Pending is highest
                    if (pendingCount === dominant && pendingCount > 0) {
                        insights.push("A high number of appointments are still pending — follow-ups or scheduling delays may be occurring.");
                    }

                    // 2️⃣ PRIORITY: Cancelled is high
                    if (cancelled === dominant && cancelled > completed) {
                        insights.push("Cancellation rate is high this month — review cancellation reasons and patient workflow.");
                    }

                    // 3️⃣ PRIORITY: Completed is highest
                    if (completed === dominant && completed > 0) {
                        insights.push("Most appointments this month were successfully completed.");
                    }

                    // Secondary general insights
                    if (data.doctorDuties > data.nurseDuties)
                        insights.push("Doctors handled more duties compared to nurses this month.");
                    else if (data.nurseDuties > data.doctorDuties)
                        insights.push("Nurses carried more operational workload this month.");

                    if (pendingCount > 20)
                        insights.push("Workload bottleneck detected: Pending appointments exceed normal levels.");

                    if (cancelled > total * 0.3)
                        insights.push("Over 30% of appointments were cancelled — this may require administrative review.");

                    if (insights.length === 0)
                        insights.push("The system appears to be stable with balanced scheduling.");

                    // Insert into UI
                    document.getElementById("insightBox").innerHTML =
                        "<ul><li>" + insights.join("</li><li>") + "</li></ul>";
                }

                /* LOAD DATA */
                async function loadStats() {
                    const month = document.getElementById('selectMonth').value;
                    const year = document.getElementById('selectYear').value;

                    if (!month || !year) return alert("Please select month and year.");

                    const url = `https://localhost:7212/employee/getMonthShiftAndDutyReport/${month}/${year}`;
                    const statsContainer = document.getElementById('statsContainer');

                    try {
                        const res = await fetch(url);
                        if (!res.ok) throw new Error("API ERROR");

                        const data = await res.json();

                        document.getElementById('totalAppointments').textContent = data.totalAppointments || 0;
                        document.getElementById('totalDoctorDuties').textContent = data.doctorDuties || 0;
                        document.getElementById('totalNurseDuties').textContent = data.nurseDuties || 0;

                        let pending = (data.totalAppointments || 0) - (data.completed || 0) - (data.cancelled || 0);
                        pending = Math.max(0, pending);

                        const tbody = document.getElementById('appointmentsTableBody');
                        tbody.innerHTML = "";

                        (data.appointments || []).forEach(a => {
                            tbody.innerHTML += `
                                <tr>
                                    <td>AP-${a.appointment_id}</td>
                                    <td>D-${a.doctor_id}</td>
                                    <td>B-${a.bed_id}</td>
                                    <td>N-${a.nurse_assistant}</td>
                                    <td>${a.procedure}</td>
                                    <td><span class="badge ${getStatusBadge(a.status)}">${a.status}</span></td>
                                </tr>`;
                        });

                        statsContainer.classList.remove('d-none');

                        initChart(data.completed || 0, pending, data.cancelled || 0);
                        generateInsights(data, pending);

                    } catch (err) {
                        console.error(err);
                        alert("Error fetching report. Check API server.");
                    }
                }
            </script>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>