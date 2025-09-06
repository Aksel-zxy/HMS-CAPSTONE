<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Management Module</title>
    <link rel="stylesheet" href="../assets/CSS/journalentry.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Journal Management Module</h1>
            <div class="module-controls">
                <div class="dropdown-container">
                    <label for="module-dropdown">Module:</label>
                    <select id="module-dropdown">
                        <option value="all">All Modules</option>
                        <option value="billing" selected>Patient Billing</option>
                        <option value="insurance">Insurance</option>
                        <option value="supply">Supply</option>
                    </select>
                </div>
                <div class="date-filter">
                    <label for="date-from">From:</label>
                    <input type="date" id="date-from" value="2025-08-01">
                    <label for="date-to">To:</label>
                    <input type="date" id="date-to" value="2025-08-31">
                    <button class="btn-filter">Apply Filter</button>
                </div>
            </div>
        </header>

        <div class="table-container">
            <div class="table-header">
                <h2>Journal Entries</h2>
                <div class="entries-count">Showing 3 entries</div>
            </div>
            <table id="journals-table">
                <thead>
                    <tr>
                        <th class="col-id">Entry ID</th>
                        <th class="col-date">Date</th>
                        <th class="col-desc">Description</th>
                        <th class="col-ref">Ref Type</th>
                        <th class="col-status">Status</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>001</td>
                        <td>2025-08-01</td>
                        <td>Patient Bill - John Doe</td>
                        <td><span class="badge billing">Billing</span></td>
                        <td><span class="status posted">Posted</span></td>
                        <td>
                            <button class="btn-view">View Lines</button>
                            <div class="dropdown-actions">
                                <button class="btn-more">⋮</button>
                                <div class="dropdown-menu">
                                    <button class="menu-item">Edit</button>
                                    <button class="menu-item">Delete</button>
                                    <button class="menu-item">Export</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>002</td>
                        <td>2025-08-02</td>
                        <td>Insurance Payment - ABC Insurance</td>
                        <td><span class="badge insurance">Insurance</span></td>
                        <td><span class="status posted">Posted</span></td>
                        <td>
                            <button class="btn-view">View Lines</button>
                            <div class="dropdown-actions">
                                <button class="btn-more">⋮</button>
                                <div class="dropdown-menu">
                                    <button class="menu-item">Edit</button>
                                    <button class="menu-item">Delete</button>
                                    <button class="menu-item">Export</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>003</td>
                        <td>2025-08-03</td>
                        <td>Medical Supplies Purchase</td>
                        <td><span class="badge supply">Supply</span></td>
                        <td><span class="status draft">Draft</span></td>
                        <td>
                            <button class="btn-view">View Lines</button>
                            <div class="dropdown-actions">
                                <button class="btn-more">⋮</button>
                                <div class="dropdown-menu">
                                    <button class="menu-item">Edit</button>
                                    <button class="menu-item">Delete</button>
                                    <button class="menu-item">Export</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="actions">
            <button id="add-entry" class="btn-primary">
                <span class="icon">+</span> Add Journal Entry
            </button>
            <div class="bulk-actions">
                <select id="bulk-action">
                    <option value="">Bulk Actions</option>
                    <option value="post">Post Selected</option>
                    <option value="delete">Delete Selected</option>
                    <option value="export">Export Selected</option>
                </select>
                <button class="btn-apply">Apply</button>
            </div>
        </div>

        <div class="summary">
            <div class="summary-item">
                <span class="label">Total Entries:</span>
                <span class="value">3</span>
            </div>
            <div class="summary-item">
                <span class="label">Posted:</span>
                <span class="value">2</span>
            </div>
            <div class="summary-item">
                <span class="label">Draft:</span>
                <span class="value">1</span>
            </div>
        </div>
    </div>

    <script>
        // Basic functionality for the UI
        document.getElementById('add-entry').addEventListener('click', function() {
            alert('Add Journal Entry functionality would open a form here.');
        });

        document.getElementById('module-dropdown').addEventListener('change', function() {
            alert(`Filtering by module: ${this.options[this.selectedIndex].text}`);
        });

        document.querySelector('.btn-filter').addEventListener('click', function() {
            const fromDate = document.getElementById('date-from').value;
            const toDate = document.getElementById('date-to').value;
            alert(`Filtering from ${fromDate} to ${toDate}`);
        });

        const viewButtons = document.querySelectorAll('.btn-view');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const entryId = this.parentElement.parentElement.firstElementChild.textContent;
                alert(`View lines for entry ${entryId}`);
            });
        });

        // Dropdown menu functionality
        const moreButtons = document.querySelectorAll('.btn-more');
        moreButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const menu = this.nextElementSibling;
                
                // Close any other open menus
                document.querySelectorAll('.dropdown-menu').forEach(m => {
                    if (m !== menu) m.classList.remove('show');
                });
                
                menu.classList.toggle('show');
            });
        });

        // Close dropdowns when clicking elsewhere
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        });

        // Prevent dropdown from closing when clicking inside it
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html> 