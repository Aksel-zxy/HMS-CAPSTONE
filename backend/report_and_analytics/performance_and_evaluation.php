<?php
$user = [
    'fname' => 'Test',
    'lname' => 'User'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        .report-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-radius: 12px;
            /* Using a slightly darker shadow for dashboard tiles */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            height: 100%;
            /* Ensure all tiles are the same height */
        }

        .report-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
        }

        /* The performance view is a full-width container, so we don't use the simple grid-container here */
        /* .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        } */

        body {
            background: #f8f9fa;
        }

        /* Custom styles for the table and badges to maintain a premium/clean look */
        .table-performance-header th {
            background-color: #343a40 !important;
            /* Darker header */
            color: white;
        }

        .performance-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        /* Custom badge colors for scores */
        .badge-score-excellent {
            background-color: #198754 !important;
        }

        .badge-score-proficient {
            background-color: #0dcaf0 !important;
        }

        .badge-score-needs {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        .badge-score-unsatisfactory {
            background-color: #dc3545 !important;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <aside id="sidebar" class="sidebar-toggle">
            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <li class="sidebar-item">
                <a href="report_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#staffMgmt"
                    aria-expanded="true" aria-controls="staffMgmt">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Doctor and Nurse Management</span>
                </a>

                <ul id="staffMgmt" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../Employee/doctor.php" class="sidebar-link">Doctors</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/nurse.php" class="sidebar-link">Nurses</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Other Staff</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item active">
                <a href="report_dashboard.php" class="sidebar-link">
                    Reporting & Analytics
                </a>
            </li>
        </aside>

        <div class="main">
            <div class="container my-5">
                <h2 class="text-center mb-5 fw-bold text-dark">Employee Performance & Evaluation REPORT</h2>

                <div class="row mb-5 text-center g-4">
                    <div class="col-md-4">
                        <div class="card report-card bg-primary text-white p-2">
                            <div class="card-body">
                                <i class="bi bi-people-fill h4 mb-2"></i>
                                <h5>Total Employees Evaluated</h5>
                                <h3 id="totalEmployees" class="display-6 fw-bold">125</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card report-card bg-success text-white p-2">
                            <div class="card-body">
                                <i class="bi bi-bar-chart-line-fill h4 mb-2"></i>
                                <h5>Avg. Performance Score</h5>
                                <h3 id="averageScore" class="display-6 fw-bold">4.1 / 5</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card report-card bg-warning text-dark p-2">
                            <div class="card-body">
                                <i class="bi bi-exclamation-triangle-fill h4 mb-2"></i>
                                <h5>Low Performers (&lt; 3.0)</h5>
                                <h3 id="lowPerformers" class="display-6 fw-bold">8</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card performance-card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center p-3">
                        <h5 class="mb-0 fw-light">Detailed Employee Performance Data</h5>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-performance-header">
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Average Score</th>
                                    <th>Performance Level</th>
                                    <th>Evaluation Period</th>
                                    <th>No. of Evaluations</th>
                                    <th>Last Evaluated</th>
                                </tr>
                            </thead>
                            <tbody id="performanceTableBody">
                                <tr>
                                    <td>E-001</td>
                                    <td><span class="badge badge-score-excellent p-2">4.8</span></td>
                                    <td>Excellent</td>
                                    <td>2024 Q3</td>
                                    <td>3</td>
                                    <td>2024-09-30</td>
                                </tr>
                                <tr>
                                    <td>E-002</td>
                                    <td><span class="badge badge-score-needs p-2">3.1</span></td>
                                    <td>Needs Improvement</td>
                                    <td>2024 Q3</td>
                                    <td>2</td>
                                    <td>2024-08-15</td>
                                </tr>
                                <tr>
                                    <td>E-003</td>
                                    <td><span class="badge badge-score-proficient p-2">4.2</span></td>
                                    <td>Proficient</td>
                                    <td>2024 Q3</td>
                                    <td>4</td>
                                    <td>2024-09-30</td>
                                </tr>
                                <tr>
                                    <td>E-004</td>
                                    <td><span class="badge badge-score-unsatisfactory p-2">2.5</span></td>
                                    <td>Unsatisfactory</td>
                                    <td>2024 Q2</td>
                                    <td>1</td>
                                    <td>2024-06-30</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer text-muted bg-light border-top-0 rounded-bottom-4">
                        <small>Data reflects cumulative performance up to the most recent evaluation period.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        // Placeholder JavaScript for populating the data.
        // You would use an AJAX call here to fetch the actual employee data.

        function getScoreBadgeClass(score) {
            if (score >= 4.5) return 'badge-score-excellent';
            if (score >= 3.5) return 'badge-score-proficient';
            if (score >= 3.0) return 'badge-score-needs';
            return 'badge-score-unsatisfactory';
        }

        // This function will be called when you load your data
        function renderPerformanceData(employeeData) {
            const tableBody = document.getElementById('performanceTableBody');
            tableBody.innerHTML = ''; // Clear existing data

            employeeData.forEach(employee => {
                const score = employee.average_score || 0;
                const badgeClass = getScoreBadgeClass(score);

                const row = `
                    <tr>
                        <td>${employee.employee_id}</td>
                        <td>${employee.employee_name || 'N/A'}</td>
                        <td><span class="badge ${badgeClass} p-2">${score.toFixed(1)}</span></td>
                        <td>${employee.performance_level || 'N/A'}</td>
                        <td>${employee.evaluation_period || 'N/A'}</td>
                        <td>${employee.number_of_evaluations || 0}</td>
                        <td>${employee.last_evaluated || 'N/A'}</td>
                        <td><button class="btn btn-sm btn-info text-white">Details</button></td>
                    </tr>
                `;
                tableBody.insertAdjacentHTML('beforeend', row);
            });
        }
    </script>
</body>

</html>