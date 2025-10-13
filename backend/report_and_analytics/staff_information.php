<?php include 'header.php' ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Staff Report — Employee Directory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .report-header {
            background: linear-gradient(90deg, #007bff 0%, #6610f2 100%);
            color: white;
            border-radius: 0.75rem;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .report-header h3 {
            margin: 0;
            font-weight: 600;
        }

        .report-header .small-muted {
            color: rgba(255, 255, 255, 0.85);
        }

        .card-summary {
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .card-summary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        }

        .table-report th {
            background: #f8f9fa;
            border-top: 2px solid #dee2e6;
        }

        .table-report td,
        .table-report th {
            vertical-align: middle;
        }

        .table-report tbody tr:hover {
            background-color: #f1f3f5;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #495057;
        }

        .small-muted {
            color: #6c757d;
            font-size: 0.9em;
        }

        .modal-header {
            background: #0d6efd;
            color: white;
        }

        .badge-status {
            font-size: 0.85em;
            padding: 0.35em 0.6em;
        }

        @media print {

            .btn,
            .pagination,
            #searchInput,
            #refreshBtn {
                display: none !important;
            }

            .card,
            .modal {
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
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
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="report-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Staff Report — Employee Directory</h3>
                    <div class="small-muted">Comprehensive report of all active staff and medical professionals</div>
                </div>
            </div>

            <!-- Summary -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class=" card card-summary border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-1">Total Employees</h6>
                            <h3 id="summaryTotal">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-summary border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-1">Active Staff</h6>
                            <h3 id="summaryActive">0</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <input id="searchInput" class="form-control w-50" placeholder="🔍 Search by name, ID, or email">
                <button id="refreshBtn" class="btn btn-outline-primary">Refresh</button>
            </div>

            <!-- Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-report align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>#</th>
                                    <th>Employee</th>
                                    <th>Role / Dept</th>
                                    <th>Specialization</th>
                                    <th>Employment</th>
                                    <th>Contact</th>
                                    <th>Hire Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="employeesTbody">
                                <tr>
                                    <td colspan="9" class="text-center small-muted py-4">Loading data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pager -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div id="pagerInfo" class="small-muted"></div>
                <ul class="pagination pagination-sm mb-0" id="pager"></ul>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Employee Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="empDetails"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const PAGE_SIZE = 12;
        let employees = [];
        let filtered = [];
        let currentPage = 1;

        async function loadEmployees() {
            try {
                const res = await fetch("http://localhost:5288/employee/getStaffInformation");
                if (!res.ok) throw new Error("Failed to fetch data");
                const data = await res.json();

                employees = data.employees.map(e => {
                    const [first, ...rest] = e.fullName.split(" ");
                    return {
                        id: e.employeeId,
                        first: first,
                        last: rest.join(" "),
                        dept: e.department || "—",
                        role: e.role || "—",
                        spec: e.specialization || "—",
                        empType: e.employmentStatus || "—",
                        contact: e.contact || "—",
                        hire: e.hireDate || "—",
                        status: e.status || "—"
                    };
                });

                filtered = employees.slice();
                document.getElementById("summaryTotal").textContent = data.totalEmployees;
                document.getElementById("summaryActive").textContent = data.activeStaff;
                renderPage();

            } catch (err) {
                console.error(err);
                document.getElementById("employeesTbody").innerHTML = `
                    <tr><td colspan="9" class="text-center text-danger py-4">Failed to load staff information.</td></tr>`;
            }
        }

        function initials(a, b) {
            return ((a?.[0] || "") + (b?.[0] || "")).toUpperCase() || "?";
        }

        function renderPage() {
            const start = (currentPage - 1) * PAGE_SIZE;
            const page = filtered.slice(start, start + PAGE_SIZE);
            const tbody = document.getElementById("employeesTbody");

            if (!page.length) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center small-muted py-4">No results.</td></tr>`;
                return;
            }

            tbody.innerHTML = page.map((e, i) => `
                <tr>
                    <td>${start + i + 1}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar">${initials(e.first, e.last)}</div>
                            <div><strong>${e.first} ${e.last}</strong><div class="small-muted">${e.id}</div></div>
                        </div>
                    </td>
                    <td><strong>${e.role}</strong><div class="small-muted">${e.dept}</div></td>
                    <td>${e.spec}</td>
                    <td>${e.empType}</td>
                    <td>${e.contact}</td>
                    <td>${e.hire}</td>
                    <td><span class="badge bg-success badge-status">${e.status}</span></td>
                    <td><button class="btn btn-sm btn-primary" onclick="viewDetails(${e.id})">View</button></td>
                </tr>`).join("");

            renderPager();
        }

        function renderPager() {
            const total = filtered.length;
            const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
            const pager = document.getElementById("pager");
            pager.innerHTML = "";

            for (let i = 1; i <= pages; i++) {
                const li = document.createElement("li");
                li.className = "page-item " + (i === currentPage ? "active" : "");
                li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                li.onclick = e => {
                    e.preventDefault();
                    currentPage = i;
                    renderPage();
                };
                pager.appendChild(li);
            }
            document.getElementById("pagerInfo").textContent =
                `Showing ${(currentPage - 1) * PAGE_SIZE + 1}-${Math.min(total, currentPage * PAGE_SIZE)} of ${total}`;
        }

        function viewDetails(id) {
            const e = employees.find(x => x.id === id);
            if (!e) return;

            document.getElementById("empDetails").innerHTML = `
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="avatar mb-3 mx-auto">${initials(e.first, e.last)}</div>
                            <h5>${e.first} ${e.last}</h5>
                            <p class="small-muted mb-1">${e.role} — ${e.dept}</p>
                            <span class="badge bg-success">${e.status}</span>
                            <hr>
                            <p class="small text-start">
                                <strong>Contact:</strong> ${e.contact}<br>
                                <strong>Employment:</strong> ${e.empType}<br>
                                <strong>Hired:</strong> ${e.hire}
                            </p>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="border rounded p-3">
                            <h6>Professional Details</h6>
                            <p><strong>Specialization:</strong> ${e.spec}</p>
                            <p class="small-muted mb-0">Report generated on ${new Date().toLocaleString()}</p>
                        </div>
                    </div>
                </div>`;
            new bootstrap.Modal('#employeeModal').show();
        }

        document.getElementById("searchInput").addEventListener("input", () => {
            const q = document.getElementById("searchInput").value.toLowerCase();
            filtered = !q ? employees : employees.filter(e =>
                (`${e.id} ${e.first} ${e.last} ${e.role} ${e.dept}`.toLowerCase().includes(q))
            );
            currentPage = 1;
            renderPage();
        });

        document.getElementById("refreshBtn").addEventListener("click", loadEmployees);

        // Load data on start
        loadEmployees();
    </script>
</body>

</html>