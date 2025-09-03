<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Accounts Management</title>
    <link rel="stylesheet" href="../assets/CSS/journalaccount.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Journal Accounts</h1>
            <div class="search-container">
                <input type="text" id="search" placeholder="Search accounts...">
                <button class="search-btn">🔍</button>
            </div>
        </header>

        <div class="table-container">
            <table id="accounts-table">
                <thead>
                    <tr>
                        <th>Account ID</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>101</td>
                        <td>Cash/Bank</td>
                        <td><span class="badge asset">Asset</span></td>
                        <td class="balance">20,000</td>
                        <td>
                            <button class="btn-edit">Edit</button>
                            <button class="btn-archive">Archive</button>
                        </td>
                    </tr>
                    <tr>
                        <td>102</td>
                        <td>Accounts Receivable</td>
                        <td><span class="badge asset">Asset</span></td>
                        <td class="balance">12,000</td>
                        <td>
                            <button class="btn-edit">Edit</button>
                            <button class="btn-archive">Archive</button>
                        </td>
                    </tr>
                    <tr>
                        <td>201</td>
                        <td>Accounts Payable</td>
                        <td><span class="badge liability">Liability</span></td>
                        <td class="balance">8,000</td>
                        <td>
                            <button class="btn-edit">Edit</button>
                            <button class="btn-archive">Archive</button>
                        </td>
                    </tr>
                    <tr>
                        <td>301</td>
                        <td>Service Revenue</td>
                        <td><span class="badge revenue">Revenue</span></td>
                        <td class="balance">15,000</td>
                        <td>
                            <button class="btn-edit">Edit</button>
                            <button class="btn-archive">Archive</button>
                        </td>
                    </tr>
                    <tr>
                        <td>401</td>
                        <td>Supplies Expense</td>
                        <td><span class="badge expense">Expense</span></td>
                        <td class="balance">4,000</td>
                        <td>
                            <button class="btn-edit">Edit</button>
                            <button class="btn-archive">Archive</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="actions">
            <button id="add-account" class="btn-primary">Add Account</button>
        </div>

        <div class="summary">
            <h2>Account Summary</h2>
            <div class="summary-cards">
                <div class="card">
                    <h3>Total Assets</h3>
                    <p class="amount">32,000</p>
                </div>
                <div class="card">
                    <h3>Total Liabilities</h3>
                    <p class="amount">8,000</p>
                </div>
                <div class="card">
                    <h3>Total Revenue</h3>
                    <p class="amount">15,000</p>
                </div>
                <div class="card">
                    <h3>Total Expenses</h3>
                    <p class="amount">4,000</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Basic functionality for the UI
        document.getElementById('add-account').addEventListener('click', function() {
            alert('Add Account functionality would open a form here.');
        });

        const editButtons = document.querySelectorAll('.btn-edit');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const accountId = this.parentElement.parentElement.firstElementChild.textContent;
                alert(`Edit account ${accountId} functionality would open here.`);
            });
        });

        const archiveButtons = document.querySelectorAll('.btn-archive');
        archiveButtons.forEach(button => {
            button.addEventListener('click', function() {
                const accountId = this.parentElement.parentElement.firstElementChild.textContent;
                if(confirm(`Are you sure you want to archive account ${accountId}?`)) {
                    alert(`Account ${accountId} archived successfully.`);
                }
            });
        });

        // Search functionality
        document.getElementById('search').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('#accounts-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if(text.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>