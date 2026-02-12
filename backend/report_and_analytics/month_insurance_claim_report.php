<?
include 'header.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Monthly Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }

        .badge-approved {
            background-color: #198754;
        }

        .badge-denied {
            background-color: #dc3545;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?
        include 'sidebar.php'
        ?>
        <div class="container py-5">

            <h2 class="text-center mb-4">Monthly Insurance Report</h2>

            <!-- Filters -->
            <div class="row g-3 mb-4 justify-content-center">
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <select id="monthSelector" class="form-select">
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
                        <option value="12" selected>December</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select id="yearSelector" class="form-select">
                        <option>2023</option>
                        <option>2024</option>
                        <option selected>2025</option>
                        <option>2026</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button id="fetchReportBtn" class="btn btn-primary w-100">Fetch Report</button>
                </div>
            </div>

            <!-- Summary -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h6>Total Claims</h6>
                        <h3 id="totalClaims">-</h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h6>Approved</h6>
                        <h3 id="totalApproved">-</h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h6>Denied</h6>
                        <h3 id="totalDenied">-</h3>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="progress mb-4" style="height:25px">
                <div id="approvedBar" class="progress-bar bg-success">Approved</div>
                <div id="deniedBar" class="progress-bar bg-danger">Denied</div>
            </div>

            <!-- Unified Table -->
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bill</th>
                            <th>Patient</th>
                            <th>Provider</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Resolved</th>
                        </tr>
                    </thead>
                    <tbody id="claimsTableBody"></tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <button class="btn btn-outline-secondary" id="prevBtn">Previous</button>
                <span id="pageInfo"></span>
                <button class="btn btn-outline-secondary" id="nextBtn">Next</button>
            </div>

        </div>

        <script>
            let currentPage = 1;
            const pageSize = 5;

            document.getElementById('fetchReportBtn').onclick = () => {
                currentPage = 1;
                fetchReport();
            };

            document.getElementById('prevBtn').onclick = () => {
                if (currentPage > 1) {
                    currentPage--;
                    fetchReport();
                }
            };

            document.getElementById('nextBtn').onclick = () => {
                currentPage++;
                fetchReport();
            };

            async function fetchReport() {
                const month = monthSelector.value;
                const year = yearSelector.value;

                const res = await fetch(
                    `https://localhost:7212/insurance/getMonthInsuranceReport?month=${month}&year=${year}&page=${currentPage}&size=${pageSize}`
                );

                const data = await res.json();

                totalClaims.textContent = data.totalClaims;
                totalApproved.textContent = data.totalApprovedClaims;
                totalDenied.textContent = data.totalDeniedClaims;

                const approvedPct = (data.totalApprovedClaims / data.totalClaims * 100).toFixed(1);
                const deniedPct = (data.totalDeniedClaims / data.totalClaims * 100).toFixed(1);

                approvedBar.style.width = approvedPct + '%';
                deniedBar.style.width = deniedPct + '%';
                approvedBar.textContent = `Approved ${approvedPct}%`;
                deniedBar.textContent = `Denied ${deniedPct}%`;

                pageInfo.textContent = `Page ${currentPage}`;

                renderTable(data.claimsList);
            }

            function renderTable(claims) {
                claimsTableBody.innerHTML = '';

                claims.forEach(c => {
                    const badgeClass = c.status === 'approved' ?
                        'badge-approved' :
                        'badge-denied';

                    claimsTableBody.innerHTML += `
                    <tr>
                        <td>${c.insurance_claims_id}</td>
                        <td>${c.bill_id}</td>
                        <td>${c.patient_id}</td>
                        <td>${c.insurance_provider_id}</td>
                        <td>₱${c.claim_amount.toLocaleString()}</td>
                        <td><span class="badge ${badgeClass}">${c.status}</span></td>
                        <td>${c.submmited_date}</td>
                        <td>${c.resolved_date}</td>
                    </tr>
                `;
                });
            }

            fetchReport();
        </script>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </div>
</body>

</html>