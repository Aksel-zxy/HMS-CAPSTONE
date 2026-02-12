<?php
include 'header.php'
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Month Payroll Summary Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <style>
        body {
            background-color: #f5f7fa;
        }

        .dashboard-header {
            text-align: center;
            margin: 30px 0 10px 0;
        }

        .summary-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .summary-card .card-body {
            text-align: center;
        }

        .summary-card h5 {
            color: #6c757d;
            font-weight: 500;
        }

        .summary-card h2 {
            color: #007bff;
            font-weight: 700;
        }

        .table-container {
            background-color: #fff;
            border-radius: 1rem;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }

        td {
            text-align: center;
            vertical-align: middle;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php
        include 'sidebar.php'
        ?>
        <div class="container">
            <h2 class="dashboard-header">Month Payroll Summary Report</h2>

            <!-- Summary cards -->
            <div id="summaryCards" class="row g-4 text-center"></div>

            <!-- Table section -->
            <div class="table-container mt-4">
                <h4 id="reportTitle">Employee Payroll Overview</h4>
                <table class="table table-hover mt-3">
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Gross Pay</th>
                            <th>Total Deductions</th>
                            <th>Net Pay</th>
                        </tr>
                    </thead>
                    <tbody id="payrollTableBody">
                        <tr>
                            <td colspan="4" class="text-center text-muted">Loading data...</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination Controls -->
                <div class="pagination-container">
                    <button id="prevPageBtn" class="btn btn-outline-primary btn-sm" disabled>Previous</button>
                    <span id="pageInfo">Page 1</span>
                    <button id="nextPageBtn" class="btn btn-outline-primary btn-sm" disabled>Next</button>
                </div>
            </div>
        </div>

        <script>
            let currentPage = 1;
            let totalPages = 1;
            let month, year, pageSize;

            // ✅ Read query parameters from URL
            const urlParams = new URLSearchParams(window.location.search);
            month = urlParams.get("month");
            year = urlParams.get("year");
            pageSize = urlParams.get("pageSize") || 5;
            currentPage = parseInt(urlParams.get("page")) || 1;

            const tableBody = document.getElementById('payrollTableBody');
            const summaryCards = document.getElementById('summaryCards');
            const pageInfo = document.getElementById('pageInfo');
            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');
            const reportTitle = document.getElementById('reportTitle');

            // ✅ Convert numeric month to name
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            const monthName = monthNames[month - 1] || "Unknown";

            async function loadPayrollReport() {
                const url = `http://localhost:5288/payroll/getMonthPayrollSummary/${month}/${year}/${pageSize}/${currentPage}`;

                tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Loading data...</td></tr>`;
                summaryCards.innerHTML = "";
                reportTitle.textContent = `Employee Payroll Overview - ${monthName} ${year}`;

                try {
                    const response = await axios.get(url);
                    const data = response.data;

                    if (!data || !data.summaryList || data.summaryList.length === 0) {
                        tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">No payroll data available for ${monthName} ${year}.</td></tr>`;
                        prevBtn.disabled = true;
                        nextBtn.disabled = true;
                        return;
                    }

                    // --- Update summary cards ---
                    summaryCards.innerHTML = `
          <div class="col-md-3">
              <div class="card summary-card"><div class="card-body">
                  <h5>Total Employees</h5><h2>${data.totalEmployees}</h2>
              </div></div>
          </div>
          <div class="col-md-3">
              <div class="card summary-card"><div class="card-body">
                  <h5>Total Gross Pay</h5><h2>₱${Number(data.totalGrossPay).toLocaleString(undefined, { minimumFractionDigits: 2 })}</h2>
              </div></div>
          </div>
          <div class="col-md-3">
              <div class="card summary-card"><div class="card-body">
                  <h5>Total Deductions</h5><h2>₱${Number(data.totalDeductions).toLocaleString(undefined, { minimumFractionDigits: 2 })}</h2>
              </div></div>
          </div>
          <div class="col-md-3">
              <div class="card summary-card"><div class="card-body">
                  <h5>Total Net Pay</h5><h2>₱${Number(data.totalNetPay).toLocaleString(undefined, { minimumFractionDigits: 2 })}</h2>
              </div></div>
          </div>`;

                    // --- Populate table ---
                    tableBody.innerHTML = data.summaryList.map(row => `
          <tr>
            <td>${row.employeeName}</td>
            <td>₱${Number(row.grossPay).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
            <td>₱${Number(row.totalDeductions).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
            <td><strong>₱${Number(row.netPay).toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong></td>
          </tr>`).join('');

                    // --- Handle pagination ---
                    totalPages = data.totalPages ?? Math.ceil(data.totalEmployees / pageSize);
                    pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
                    prevBtn.disabled = currentPage <= 1;
                    nextBtn.disabled = currentPage >= totalPages;

                } catch (error) {
                    console.error(error);
                    tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">Error loading payroll data.</td></tr>`;
                }
            }

            // --- Pagination Events ---
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    updateUrlParams();
                    loadPayrollReport();
                }
            });

            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    updateUrlParams();
                    loadPayrollReport();
                }
            });

            // ✅ Keep URL updated when page changes
            function updateUrlParams() {
                const newUrl = `${window.location.pathname}?month=${month}&year=${year}&pageSize=${pageSize}&page=${currentPage}`;
                window.history.pushState({}, '', newUrl);
            }

            // Auto-load on page open
            window.addEventListener('DOMContentLoaded', loadPayrollReport);
        </script>
    </div>
</body>

</html>