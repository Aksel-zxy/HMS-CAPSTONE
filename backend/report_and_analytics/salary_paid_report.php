<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Hospital Monthly Payroll Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
</head>

<body class="bg-light p-4">
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="admin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Doctor and Nurse Management</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
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

            <li class="sidebar-item">
                <a href="report_dashboard.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Reporting and Analytics</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="paycycle_report.php" class="sidebar-link">Employee Paycycle Report</a>
                        <a href="annualPayroll_Report.php" class="sidebar-link">Employee Annual Payroll Report</a>
                        <a href="hospital_income_report.php" class="sidebar-link">Hospital month income statement</a>
                    </li>
                </ul>
            </li>
        </aside>
        <div class="container">
            <h2 class="mb-4 text-center">Hospital Monthly Payroll Report</h2>

            <!-- Selection Form -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select id="month" class="form-select">
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
                    <label for="year" class="form-label">Year</label>
                    <select id="year" class="form-select"></select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button onclick="loadReport()" class="btn btn-primary w-100">Load Report</button>
                </div>
            </div>

            <!-- Payroll Table -->
            <div class="table-responsive">
                <table id="payrollTable" class="table table-bordered table-striped table-hover" style="display:none;">
                    <thead class="table-dark">
                        <tr>
                            <th>Employee ID</th>
                            <th>Full Name</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Basic Salary</th>
                            <th>Overtime Pay</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                            <th>Total Salary Paid</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <td colspan="8" class="text-end fw-bold">Grand Total Salary Paid:</td>
                            <td id="totalSalaryCell" class="fw-bold"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Populate year dropdown dynamically
        const yearSelect = document.getElementById("year");
        const currentYear = new Date().getFullYear();
        for (let y = currentYear + 1; y >= currentYear - 10; y--) {
            const opt = document.createElement("option");
            opt.value = y;
            opt.textContent = y;
            if (y === currentYear) opt.selected = true;
            yearSelect.appendChild(opt);
        }

        async function loadReport() {
            const month = document.getElementById("month").value;
            const year = document.getElementById("year").value;
            const url = `http://localhost:5288/Hr/getHospitalMonthlyPayrollReport/${month}/${year}`;

            try {
                const response = await fetch(url, {
                    headers: {
                        "Accept": "application/json"
                    }
                });
                if (!response.ok) throw new Error("Failed to fetch payroll data");

                const data = await response.json();
                const tbody = document.querySelector("#payrollTable tbody");
                tbody.innerHTML = "";

                let totalSalary = 0;

                if (!data || data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No records found for ${month}/${year}</td></tr>`;
                } else {
                    data.forEach(row => {
                        const tr = document.createElement("tr");
                        tr.innerHTML = `
              <td>${row.employeeId}</td>
              <td>${row.fullName}</td>
              <td>${row.department}</td>
              <td>${row.role}</td>
              <td>${formatCurrency(row.basicSalary)}</td>
              <td>${formatCurrency(row.overtimePay)}</td>
              <td>${formatCurrency(row.deductions)}</td>
              <td>${formatCurrency(row.netPay)}</td>
              <td>${formatCurrency(row.totalSalaryPaid)}</td>
            `;
                        tbody.appendChild(tr);

                        totalSalary += row.totalSalaryPaid || 0;
                    });
                }

                document.getElementById("totalSalaryCell").textContent = formatCurrency(totalSalary);
                document.getElementById("payrollTable").style.display = "table";

            } catch (err) {
                alert("Error: " + err.message);
            }
        }

        function formatCurrency(value) {
            if (value == null) return "-";
            return "₱ " + parseFloat(value).toLocaleString("en-PH", {
                minimumFractionDigits: 2
            });
        }
    </script>

</body>

</html>