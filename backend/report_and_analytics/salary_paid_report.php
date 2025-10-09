<?php include 'header.php' ?>

<body class="bg-light p-4">
    <div class="d-flex">
        <?php include 'sidebar.php' ?>
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
                const url = `http://localhost:5288/payroll/getHospitalMonthlyPayrollReport/${month}/${year}`;

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
    </div>
</body>

</html>