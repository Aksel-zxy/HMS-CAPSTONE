<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Patient Insurance Claim Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-4">

    <div class="container">
        <h2 class="mb-4 text-center">Patient Insurance Claim Report</h2>

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
                <button onclick="loadClaims()" class="btn btn-primary w-100">Load Report</button>
            </div>
        </div>

        <!-- Claims Table -->
        <div class="table-responsive">
            <table id="claimsTable" class="table table-bordered table-striped table-hover" style="display:none;">
                <thead class="table-dark">
                    <tr>
                        <th>Claim ID</th>
                        <th>Patient Name</th>
                        <th>Insurance Provider</th>
                        <th>Policy Number</th>
                        <th>Treatment/Service</th>
                        <th>Date of Service</th>
                        <th>Status</th>
                        <th>Claimed Amount</th>
                        <th>Approved Amount</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot class="table-secondary">
                    <tr>
                        <td colspan="7" class="text-end fw-bold">Total Approved Claims:</td>
                        <td colspan="2" id="totalClaimsCell" class="fw-bold"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Populate year dropdown
        const yearSelect = document.getElementById("year");
        const currentYear = new Date().getFullYear();
        for (let y = currentYear + 1; y >= currentYear - 10; y--) {
            const opt = document.createElement("option");
            opt.value = y;
            opt.textContent = y;
            if (y === currentYear) opt.selected = true;
            yearSelect.appendChild(opt);
        }

        // 🧪 Test Data (instead of API call)
        const testClaims = [{
                claimId: "C1001",
                patientName: "Juan Dela Cruz",
                insuranceProvider: "PhilHealth",
                policyNumber: "PH-2025-001",
                service: "Appendectomy",
                dateOfService: "2025-09-01",
                status: "Approved",
                claimedAmount: 50000,
                approvedAmount: 45000
            },
            {
                claimId: "C1002",
                patientName: "Maria Santos",
                insuranceProvider: "Maxicare",
                policyNumber: "MX-2025-009",
                service: "Cesarean Delivery",
                dateOfService: "2025-09-03",
                status: "Pending",
                claimedAmount: 80000,
                approvedAmount: 0
            },
            {
                claimId: "C1003",
                patientName: "Pedro Reyes",
                insuranceProvider: "Intellicare",
                policyNumber: "IC-2025-015",
                service: "Physical Therapy (10 sessions)",
                dateOfService: "2025-09-05",
                status: "Approved",
                claimedAmount: 15000,
                approvedAmount: 15000
            },
            {
                claimId: "C1004",
                patientName: "Ana Cruz",
                insuranceProvider: "PhilHealth",
                policyNumber: "PH-2025-023",
                service: "MRI Scan",
                dateOfService: "2025-09-08",
                status: "Denied",
                claimedAmount: 20000,
                approvedAmount: 0
            }
        ];

        function loadClaims() {
            const tbody = document.querySelector("#claimsTable tbody");
            tbody.innerHTML = "";
            let totalApproved = 0;

            if (!testClaims || testClaims.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No insurance claims found</td></tr>`;
            } else {
                testClaims.forEach(claim => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
            <td>${claim.claimId}</td>
            <td>${claim.patientName}</td>
            <td>${claim.insuranceProvider}</td>
            <td>${claim.policyNumber}</td>
            <td>${claim.service}</td>
            <td>${formatDate(claim.dateOfService)}</td>
            <td>${claim.status}</td>
            <td>${formatCurrency(claim.claimedAmount)}</td>
            <td>${formatCurrency(claim.approvedAmount)}</td>
          `;
                    tbody.appendChild(tr);

                    totalApproved += claim.approvedAmount || 0;
                });
            }

            document.getElementById("totalClaimsCell").textContent = formatCurrency(totalApproved);
            document.getElementById("claimsTable").style.display = "table";
        }

        function formatCurrency(value) {
            if (value == null) return "-";
            return "₱ " + parseFloat(value).toLocaleString("en-PH", {
                minimumFractionDigits: 2
            });
        }

        function formatDate(dateStr) {
            if (!dateStr) return "-";
            const d = new Date(dateStr);
            return d.toLocaleDateString("en-PH", {
                year: "numeric",
                month: "short",
                day: "numeric"
            });
        }
    </script>

</body>

</html>