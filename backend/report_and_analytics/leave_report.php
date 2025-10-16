<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Leave Report — Employee Management</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f7f7f8;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #222;
        }

        .page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .card-sm {
            border-radius: 0.6rem;
            box-shadow: 0 6px 16px rgba(16, 24, 40, 0.04);
        }

        .table thead th {
            background: #f1f1f1;
        }

        .filter-row .form-control,
        .filter-row .btn {
            min-height: 42px;
        }

        .badge-status {
            font-size: .8rem;
            padding: .35rem .6rem;
        }

        @media (max-width:767px) {
            .page-head {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-row {
                gap: .5rem;
            }
        }
    </style>
</head>

<body>

    <div class="container-fluid py-4">
        <div class="page-head">
            <div>
                <h3 class="mb-0">Leave Report</h3>
                <div class="text-muted">Full list of employee leave requests and their approval status</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card card-sm p-3 mb-3">
            <div class="row align-items-center g-2 filter-row">
                <div class="col-md-3">
                    <input id="searchInput" type="search" class="form-control" placeholder="Search by name, id, department, reason...">
                </div>

                <div class="col-md-2">
                    <select id="statusFilter" class="form-select">
                        <option value="">All status</option>
                        <option>Pending</option>
                        <option>Approved</option>
                        <option>Rejected</option>
                        <option>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select id="typeFilter" class="form-select">
                        <option value="">All types</option>
                        <option>Sick Leave</option>
                        <option>Vacation</option>
                        <option>Maternity</option>
                        <option>Emergency</option>
                        <option>Others</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <input id="fromDate" type="date" class="form-control">
                    <input id="toDate" type="date" class="form-control">
                </div>

                <div class="col-md-2 text-end">
                    <button id="clearFilters" class="btn btn-light">Clear</button>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div class="card card-sm p-3">
                    <small class="text-muted">Total Leaves</small>
                    <h4 id="summaryTotal" class="mb-0">0</h4>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card card-sm p-3">
                    <small class="text-muted">Pending</small>
                    <h4 id="summaryPending" class="mb-0">0</h4>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card card-sm p-3">
                    <small class="text-muted">Approved</small>
                    <h4 id="summaryApproved" class="mb-0">0</h4>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card card-sm shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Leave ID</th>
                            <th>Employee</th>
                            <th>Profession / Role</th>
                            <th>Department</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Approval Date</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="leaveTbody">
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pager -->
            <div class="d-flex justify-content-between align-items-center p-3">
                <div id="pagerInfo" class="text-muted small"></div>
                <ul class="pagination pagination-sm mb-0" id="pager"></ul>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="leaveModalLabel">Leave Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group mb-3" id="modalDetails">
                        <!-- dynamic -->
                    </ul>

                    <h6>Leave Reason</h6>
                    <p id="modalReason" class="text-muted"></p>

                    <div id="medicalCertArea" class="mt-3"></div>

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /*
  Leave Report page script
  - Update API_URL to your endpoint that returns an array of objects with these fields:
    leave_id, employee_id, first_name, last_name, profession, role, department,
    leave_start_date, leave_end_date, leave_type, leave_status, approval_date,
    leave_reason, medical_cert (url or filename or null), submit_at
*/

        const API_URL = '/employee/getLeaveReports'; // <-- change to your actual API
        const PAGE_SIZE = 12;

        let leaves = []; // all data
        let filtered = []; // after search/filters
        let currentPage = 1;

        // Helpers
        function formatDate(d) {
            if (!d) return '—';
            const dt = new Date(d);
            if (isNaN(dt)) return d;
            return dt.toLocaleDateString();
        }

        function textOrDash(v) {
            return (v === null || v === undefined || v === '') ? '—' : v;
        }

        function badgeForStatus(s) {
            if (!s) return `<span class="badge bg-secondary badge-status">—</span>`;
            const key = s.toLowerCase();
            if (key.includes('pending')) return `<span class="badge bg-warning text-dark badge-status">${s}</span>`;
            if (key.includes('approve')) return `<span class="badge bg-success badge-status">${s}</span>`;
            if (key.includes('reject')) return `<span class="badge bg-danger badge-status">${s}</span>`;
            if (key.includes('cancel')) return `<span class="badge bg-secondary badge-status">${s}</span>`;
            return `<span class="badge bg-info badge-status">${s}</span>`;
        }

        // Fetch data
        async function loadData() {
            try {
                document.getElementById('leaveTbody').innerHTML = `<tr><td colspan="12" class="text-center text-muted py-4">Loading...</td></tr>`;
                const res = await fetch(API_URL);
                if (!res.ok) throw new Error('Network error ' + res.status);
                const data = await res.json();
                // assume data is either array or wrapped { leaves: [...] } - handle both
                leaves = Array.isArray(data) ? data : (Array.isArray(data.leaves) ? data.leaves : []);
                // normalize fields if needed (e.g. some endpoints use snake_case vs camelCase) - keep as-is
                filtered = leaves.slice();
                currentPage = 1;
                renderPage();
                updateSummary();
            } catch (err) {
                console.error(err);
                document.getElementById('leaveTbody').innerHTML = `<tr><td colspan="12" class="text-center text-danger py-4">Failed to load data.</td></tr>`;
            }
        }

        // Render page (pagination)
        function renderPage() {
            const start = (currentPage - 1) * PAGE_SIZE;
            const page = filtered.slice(start, start + PAGE_SIZE);
            const tbody = document.getElementById('leaveTbody');

            if (!page.length) {
                tbody.innerHTML = `<tr><td colspan="12" class="text-center text-muted py-4">No results.</td></tr>`;
                renderPager();
                return;
            }

            tbody.innerHTML = page.map((r, i) => {
                const idx = start + i + 1;
                const name = `${textOrDash(r.first_name)} ${textOrDash(r.last_name)}`;
                return `<tr>
      <td>${idx}</td>
      <td>${textOrDash(r.leave_id)}</td>
      <td><strong>${name}</strong><div class="text-muted small">ID: ${textOrDash(r.employee_id)}</div></td>
      <td>${textOrDash(r.profession)} / ${textOrDash(r.role)}</td>
      <td>${textOrDash(r.department)}</td>
      <td>${formatDate(r.leave_start_date)}</td>
      <td>${formatDate(r.leave_end_date)}</td>
      <td>${textOrDash(r.leave_type)}</td>
      <td>${badgeForStatus(r.leave_status)}</td>
      <td>${formatDate(r.approval_date)}</td>
      <td>${formatDate(r.submit_at)}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="openDetails('${encodeURIComponent(JSON.stringify(r))}')">View</button></td>
    </tr>`;
            }).join('');

            renderPager();
        }

        // Pager
        function renderPager() {
            const total = filtered.length;
            const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
            const pager = document.getElementById('pager');
            pager.innerHTML = '';
            for (let i = 1; i <= pages; i++) {
                const li = document.createElement('li');
                li.className = 'page-item ' + (i === currentPage ? 'active' : '');
                li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                li.onclick = (e) => {
                    e.preventDefault();
                    currentPage = i;
                    renderPage();
                };
                pager.appendChild(li);
            }
            document.getElementById('pagerInfo').textContent = `Showing ${(currentPage-1)*PAGE_SIZE+1}-${Math.min(total,currentPage*PAGE_SIZE)} of ${total}`;
        }

        // Filters
        function applyFilters() {
            const q = document.getElementById('searchInput').value.trim().toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;
            const from = document.getElementById('fromDate').value;
            const to = document.getElementById('toDate').value;

            filtered = leaves.filter(r => {
                // search across fields
                const hay = `${r.leave_id||''} ${r.employee_id||''} ${r.first_name||''} ${r.last_name||''} ${r.department||''} ${r.leave_reason||''}`.toString().toLowerCase();
                if (q && !hay.includes(q)) return false;

                if (status && ((r.leave_status || '').toString()) !== status) return false;
                if (type && ((r.leave_type || '').toString()) !== type) return false;

                if (from) {
                    const s = r.leave_start_date ? new Date(r.leave_start_date) : null;
                    if (!s || s < new Date(from)) return false;
                }
                if (to) {
                    const e = r.leave_end_date ? new Date(r.leave_end_date) : null;
                    if (!e || e > (new Date(to).setHours(23, 59, 59, 999))) return false;
                }
                return true;
            });

            currentPage = 1;
            renderPage();
            updateSummary();
        }

        // Summary numbers
        function updateSummary() {
            document.getElementById('summaryTotal').textContent = leaves.length;
            document.getElementById('summaryPending').textContent = leaves.filter(x => (x.leave_status || '').toLowerCase().includes('pend')).length;
            document.getElementById('summaryApproved').textContent = leaves.filter(x => (x.leave_status || '').toLowerCase().includes('approve')).length;
        }

        // Open details modal
        function openDetails(encodedJson) {
            let item;
            try {
                item = JSON.parse(decodeURIComponent(encodedJson));
            } catch (e) {
                console.error(e);
                return;
            }

            document.getElementById('leaveModalLabel').innerText = `Leave ${textOrDash(item.leave_id)} — ${textOrDash(item.first_name)} ${textOrDash(item.last_name)}`;
            const details = [
                ['Employee ID', item.employee_id],
                ['Profession', item.profession],
                ['Role', item.role],
                ['Department', item.department],
                ['Leave Start', formatDate(item.leave_start_date)],
                ['Leave End', formatDate(item.leave_end_date)],
                ['Type', item.leave_type],
                ['Status', item.leave_status],
                ['Approval Date', formatDate(item.approval_date)],
                ['Submitted At', formatDate(item.submit_at)]
            ].map(([k, v]) => `<li class="list-group-item"><strong>${k}:</strong> ${textOrDash(v)}</li>`).join('');
            document.getElementById('modalDetails').innerHTML = details;
            document.getElementById('modalReason').textContent = item.leave_reason || '—';

            const certArea = document.getElementById('medicalCertArea');
            certArea.innerHTML = '';
            if (item.medical_cert) {
                // assume medical_cert could be a full URL — adjust logic if needed
                const url = item.medical_cert;
                certArea.innerHTML = `<h6>Medical Certificate</h6>
      <p><a href="${url}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Open Certificate</a>
      <a href="${url}" download class="btn btn-sm btn-outline-secondary ms-2">Download</a></p>`;
            }

            const modal = new bootstrap.Modal(document.getElementById('leaveModal'));
            modal.show();
        }

        // CSV Export
        function exportCSV() {
            if (!filtered.length) return alert('No data to export');
            const cols = ['leave_id', 'employee_id', 'first_name', 'last_name', 'profession', 'role', 'department', 'leave_start_date', 'leave_end_date', 'leave_type', 'leave_status', 'approval_date', 'leave_reason', 'medical_cert', 'submit_at'];
            const rows = filtered.map(r => cols.map(c => `"${(r[c] !== undefined && r[c] !== null) ? String(r[c]).replace(/"/g,'""') : ''}"`).join(','));
            const csv = [cols.join(','), ...rows].join('\n');
            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `leave_report_${new Date().toISOString().slice(0,10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }

        // Print
        function printTable() {
            window.print();
        }

        // Event listeners
        document.getElementById('searchInput').addEventListener('input', () => applyFilters());
        document.getElementById('statusFilter').addEventListener('change', () => applyFilters());
        document.getElementById('typeFilter').addEventListener('change', () => applyFilters());
        document.getElementById('fromDate').addEventListener('change', () => applyFilters());
        document.getElementById('toDate').addEventListener('change', () => applyFilters());
        document.getElementById('clearFilters').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('typeFilter').value = '';
            document.getElementById('fromDate').value = '';
            document.getElementById('toDate').value = '';
            filtered = leaves.slice();
            currentPage = 1;
            renderPage();
            updateSummary();
        });
        document.getElementById('exportCsvBtn').addEventListener('click', exportCSV);
        document.getElementById('printBtn').addEventListener('click', printTable);
        document.getElementById('refreshBtn').addEventListener('click', loadData);

        // initial load
        loadData();
    </script>
</body>

</html>