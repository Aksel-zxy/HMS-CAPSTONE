<?php include 'header.php' ?>

<body class="bg-light">

    <div class="d-flex">
        <!----- Sidebar ----->
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
        <!----- End of Sidebar ----->

        <div class="container py-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 mb-0">Monthly Claims — Status Overview</h1>
                    <div class="small-muted mt-1">See quick totals and per-employee details by status</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select id="monthSelect" class="form-select form-select-sm" style="width:140px">
                        <option value="1">Jan</option>
                        <option value="2">Feb</option>
                        <option value="3">Mar</option>
                        <option value="4">Apr</option>
                        <option value="5">May</option>
                        <option value="6">Jun</option>
                        <option value="7">Jul</option>
                        <option value="8">Aug</option>
                        <option value="9" selected>Sep</option>
                        <option value="10">Oct</option>
                        <option value="11">Nov</option>
                        <option value="12">Dec</option>
                    </select>
                    <select id="yearSelect" class="form-select form-select-sm" style="width:110px"></select>
                    <button id="loadBtn" class="btn btn-primary btn-sm">Load</button>
                </div>
            </div>

            <div class="row gy-4">
                <!-- Tiles -->
                <div class="col-12 col-lg-4 tiles-col">
                    <div class="d-grid gap-3">
                        <!-- Approved -->
                        <div class="tile-card bg-white p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="tile-label text-uppercase text-muted">Approved</div>
                                    <div id="approvedCount" class="tile-count text-primary">0</div>
                                </div>
                                <div><span class="badge pill-approved rounded-pill py-2 px-3">Approved</span></div>
                            </div>
                            <hr class="my-3" />
                            <div id="approvedList" style="max-height:320px; overflow:auto;">
                                <div class="empty-state">No approved claims</div>
                            </div>
                        </div>
                        <!-- Pending -->
                        <div class="tile-card bg-white p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="tile-label text-uppercase text-muted">Pending</div>
                                    <div id="pendingCount" class="tile-count text-warning">0</div>
                                </div>
                                <div><span class="badge pill-pending rounded-pill py-2 px-3">Pending</span></div>
                            </div>
                            <hr class="my-3" />
                            <div id="pendingList" style="max-height:320px; overflow:auto;">
                                <div class="empty-state">No pending claims</div>
                            </div>
                        </div>
                        <!-- Denied -->
                        <div class="tile-card bg-white p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="tile-label text-uppercase text-muted">Denied</div>
                                    <div id="deniedCount" class="tile-count text-danger">0</div>
                                </div>
                                <div><span class="badge pill-denied rounded-pill py-2 px-3">Denied</span></div>
                            </div>
                            <hr class="my-3" />
                            <div id="deniedList" style="max-height:320px; overflow:auto;">
                                <div class="empty-state">No denied claims</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="col-12 col-lg-8">
                    <div class="card tile-card p-3 mb-4">
                        <h5 class="mb-3">Summary</h5>
                        <div class="d-flex gap-3">
                            <div>
                                <div class="fs-3 fw-bold" id="totalsAll">0</div>
                                <div class="small-muted">All claims</div>
                            </div>
                            <div>
                                <div class="fs-3 text-primary fw-bold" id="totalsApproved">0</div>
                                <div class="small-muted">Approved</div>
                            </div>
                            <div>
                                <div class="fs-3 text-warning fw-bold" id="totalsPending">0</div>
                                <div class="small-muted">Pending</div>
                            </div>
                            <div>
                                <div class="fs-3 text-danger fw-bold" id="totalsDenied">0</div>
                                <div class="small-muted">Denied</div>
                            </div>
                        </div>
                    </div>

                    <!-- Table of all claims -->
                    <div class="card tile-card p-3">
                        <h5 class="mb-3">All Claims (Table View)</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient</th>
                                        <th>Provider</th>
                                        <th>Insurance #</th>
                                        <th>Date of Service</th>
                                        <th>Remarks</th>
                                        <th>Claim Amount</th>
                                        <th>Insurance Covered</th>
                                        <th>Insurance Covered %</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="claimsTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No claims available</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function el(id) {
            return document.getElementById(id);
        }

        function formatCurrency(v) {
            return "₱ " + (+v).toLocaleString();
        }

        function formatDate(d) {
            const dt = new Date(d);
            return dt.toLocaleDateString();
        }

        function normalizeStatus(s) {
            if (!s) return "Pending";
            s = s.toLowerCase();
            if (s.includes("approve")) return "Approved";
            if (s.includes("pend")) return "Pending";
            if (s.includes("deny") || s.includes("declin")) return "Denied";
            return s;
        }


        async function loadData() {
            const m = el("monthSelect").value,
                y = el("yearSelect").value;
            const url = `http://localhost:5288/insurance/monthInsuranceClaimsReport/${m}/${y}`;
            el("approvedList").innerHTML = el("pendingList").innerHTML = el("deniedList").innerHTML =
                '<div class="empty-state">Loading…</div>';
            el("claimsTableBody").innerHTML =
                '<tr><td colspan="9" class="text-center text-muted">Loading…</td></tr>';
            try {
                const r = await fetch(url);
                let data = await r.json();
                if (!Array.isArray(data)) data = [data];
                data = data.map(d => ({
                    ...d,
                    status: normalizeStatus(d.status)
                }));

                const approved = data.filter(d => d.status === "Approved");
                const pending = data.filter(d => d.status === "Pending");
                const denied = data.filter(d => d.status === "Denied");

                // Update counts
                el("approvedCount").textContent = approved.length;
                el("pendingCount").textContent = pending.length;
                el("deniedCount").textContent = denied.length;
                el("totalsAll").textContent = data.length;
                el("totalsApproved").textContent = approved.length;
                el("totalsPending").textContent = pending.length;
                el("totalsDenied").textContent = denied.length;

                // Populate side lists
                const makeRow = (item) => {
                    const div = document.createElement("div");
                    div.className = "employee-row mb-2 p-2 border-bottom";
                    div.innerHTML = `<div style="flex:1">
                        <div class="fw-bold">${item.patientName}</div>
                        <div class="small text-muted"><strong>${item.insuranceProvider}</strong> · ${formatDate(item.dateOfService)} · #${item.insuranceNumber}</div>
                        <div class="small text-muted">${item.remarks}</div>
                        <div class="small text-muted">Covered: ${formatCurrency(item.insuranceCovered || 0)} (${item.percentageCovered?.length ? item.percentageCovered.join(", ") + "%" : "0%"})</div>
                    </div>
                    <div style="text-align:right">
                        <div class="fw-semibold">${formatCurrency(item.claimAmount)}</div>
                        <div class="small-muted">${item.status}</div>
                    </div>`;
                    return div;
                };
                el("approvedList").innerHTML = approved.length ? "" : '<div class="empty-state">No approved claims</div>';
                approved.forEach(i => el("approvedList").appendChild(makeRow(i)));
                el("pendingList").innerHTML = pending.length ? "" : '<div class="empty-state">No pending claims</div>';
                pending.forEach(i => el("pendingList").appendChild(makeRow(i)));
                el("deniedList").innerHTML = denied.length ? "" : '<div class="empty-state">No denied claims</div>';
                denied.forEach(i => el("deniedList").appendChild(makeRow(i)));

                // Populate table
                el("claimsTableBody").innerHTML = data.length ? "" : '<tr><td colspan="9" class="text-center text-muted">No claims available</td></tr>';
                data.forEach(i => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td>${i.patientName}</td>
                        <td>${i.insuranceProvider}</td>
                        <td>${i.insuranceNumber}</td>
                        <td>${formatDate(i.dateOfService)}</td>
                        <td>${i.remarks}</td>
                        <td>${formatCurrency(i.claimAmount)}</td>
                        <td>${formatCurrency(i.insuranceCovered || 0)}</td>
                        <td>${i.percentageCovered?.length ? i.percentageCovered.join(", ") + "%" : "0%"}</td>
                        <td><span class="badge ${i.status === 'Approved' ? 'bg-primary' : i.status === 'Pending' ? 'bg-warning text-dark' : 'bg-danger'}">${i.status}</span></td>
                    `;
                    el("claimsTableBody").appendChild(tr);
                });

            } catch (e) {
                console.error(e);
                el("approvedList").innerHTML = el("pendingList").innerHTML = el("deniedList").innerHTML =
                    '<div class="empty-state">Error loading data</div>';
                el("claimsTableBody").innerHTML =
                    '<tr><td colspan="9" class="text-center text-danger">Error loading data</td></tr>';
            }
        }

        (function populateYears() {
            const y = new Date().getFullYear();
            for (let i = y; i >= y - 5; i--) {
                const o = document.createElement("option");
                o.value = i;
                o.textContent = i;
                if (i === y) o.selected = true;
                el("yearSelect").appendChild(o);
            }
        })();

        document.getElementById("loadBtn").addEventListener("click", loadData);
        loadData();
    </script>
</body>

</html>