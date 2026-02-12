<?php
include 'header.php'
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Monthly Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>

<body class="bg-light">
    <div class="d-flex">
        <?php
        include 'sidebar.php'
        ?>

        <div class="container py-4">
            <h2 class="text-center mb-4">Payroll Monthly Summary</h2>

            <!-- Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <table class="table table-hover text-center align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>Month</th>
                                <th>Total Deductions</th>
                                <th>Total Net Pay</th>
                                <th>Total Employees</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="summaryTableBody">
                            <tr>
                                <td colspan="5" class="text-muted">Loading data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            const summaryTableBody = document.getElementById("summaryTableBody");

            async function loadSummary() {
                const year = new Date().getFullYear(); // ✅ Automatically use current year
                summaryTableBody.innerHTML = `<tr><td colspan="5" class="text-muted">Loading data...</td></tr>`;

                try {
                    const url = `http://localhost:5288/payroll/getYearPayrollSummary/${year}`;
                    const res = await axios.get(url);
                    const data = res.data || [];

                    if (data.length === 0) {
                        summaryTableBody.innerHTML = `<tr><td colspan="5" class="text-danger">No payroll data available for ${year}.</td></tr>`;
                        return;
                    }

                    let html = '';
                    data.forEach(m => {
                        html += `
            <tr>
              <td>${new Date(0, m.month - 1).toLocaleString('default', { month: 'long' })}</td>
              <td>₱${Number(m.totalDeductions).toLocaleString()}</td>
              <td>₱${Number(m.totalNetPay).toLocaleString()}</td>
              <td>${m.totalEmployees}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary" 
                  onclick="redirectToPayroll(${m.month}, ${year})">View</button>
              </td>
            </tr>`;
                    });

                    summaryTableBody.innerHTML = html;
                } catch (err) {
                    console.error(err);
                    summaryTableBody.innerHTML = `<tr><td colspan="5" class="text-danger">Failed to load data.</td></tr>`;
                }
            }

            function redirectToPayroll(month, year) {
                const pageSize = 5;
                const currentPage = 1;
                // ✅ Correct query parameter format
                window.location.href = `http://localhost:8080/backend/report_and_analytics/payroll_data.php?month=${month}&year=${year}&pageSize=${pageSize}&page=${currentPage}`;
            }

            // Auto-load when page opens
            loadSummary();
        </script>
    </div>
</body>

</html>