<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Accounts Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7f9;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        h1 {
            color: #2c3e50;
            font-size: 28px;
        }

        .search-container {
            display: flex;
            align-items: center;
        }

        #search {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            width: 250px;
            font-size: 14px;
        }

        .search-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .asset {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .liability {
            background-color: #ffebee;
            color: #c62828;
        }

        .revenue {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .expense {
            background-color: #fff8e1;
            color: #f57f17;
        }

        .balance {
            font-weight: 600;
            color: #2c3e50;
        }

        .btn-edit, .btn-archive {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            margin-right: 5px;
        }

        .btn-edit {
            background-color: #3498db;
            color: white;
        }

        .btn-archive {
            background-color: #e74c3c;
            color: white;
        }

        .actions {
            margin-bottom: 30px;
        }

        .btn-primary {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
        }

        .summary h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .card h3 {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .amount {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }
    </style>
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
                    <!-- Accounts will be populated by JavaScript -->
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
                    <p id="total-assets" class="amount">0</p>
                </div>
                <div class="card">
                    <h3>Total Liabilities</h3>
                    <p id="total-liabilities" class="amount">0</p>
                </div>
                <div class="card">
                    <h3>Total Revenue</h3>
                    <p id="total-revenue" class="amount">0</p>
                </div>
                <div class="card">
                    <h3>Total Expenses</h3>
                    <p id="total-expenses" class="amount">0</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Backend Logic Simulation
        class JournalAccountManager {
            constructor() {
                this.accounts = this.loadAccounts();
                this.currentId = this.accounts.length > 0 ? Math.max(...this.accounts.map(a => a.account_id)) + 1 : 1;
                this.init();
            }

            // Load accounts from localStorage or use default data
            loadAccounts() {
                const savedAccounts = localStorage.getItem('journalAccounts');
                if (savedAccounts) {
                    return JSON.parse(savedAccounts);
                }
                
                // Default accounts if none exist
                return [
                    { account_id: 101, account_name: "Cash/Bank", account_type: "Asset", balance: 20000 },
                    { account_id: 102, account_name: "Accounts Receivable", account_type: "Asset", balance: 12000 },
                    { account_id: 201, account_name: "Accounts Payable", account_type: "Liability", balance: 8000 },
                    { account_id: 301, account_name: "Service Revenue", account_type: "Revenue", balance: 15000 },
                    { account_id: 401, account_name: "Supplies Expense", account_type: "Expense", balance: 4000 }
                ];
            }

            // Save accounts to localStorage
            saveAccounts() {
                localStorage.setItem('journalAccounts', JSON.stringify(this.accounts));
            }

            // Initialize the application
            init() {
                this.renderAccounts();
                this.calculateSummary();
                this.setupEventListeners();
            }

            // Render accounts to the table
            renderAccounts() {
                const tbody = document.querySelector('#accounts-table tbody');
                tbody.innerHTML = '';
                
                this.accounts.forEach(account => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${account.account_id}</td>
                        <td>${account.account_name}</td>
                        <td><span class="badge ${account.account_type.toLowerCase()}">${account.account_type}</span></td>
                        <td class="balance">${this.formatCurrency(account.balance)}</td>
                        <td>
                            <button class="btn-edit" data-id="${account.account_id}">Edit</button>
                            <button class="btn-archive" data-id="${account.account_id}">Archive</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }

            // Format currency values
            formatCurrency(amount) {
                return new Intl.NumberFormat('en-US').format(amount);
            }

            // Calculate and display summary
            calculateSummary() {
                const assets = this.accounts
                    .filter(a => a.account_type === 'Asset')
                    .reduce((sum, account) => sum + account.balance, 0);
                
                const liabilities = this.accounts
                    .filter(a => a.account_type === 'Liability')
                    .reduce((sum, account) => sum + account.balance, 0);
                
                const revenue = this.accounts
                    .filter(a => a.account_type === 'Revenue')
                    .reduce((sum, account) => sum + account.balance, 0);
                
                const expenses = this.accounts
                    .filter(a => a.account_type === 'Expense')
                    .reduce((sum, account) => sum + account.balance, 0);
                
                document.getElementById('total-assets').textContent = this.formatCurrency(assets);
                document.getElementById('total-liabilities').textContent = this.formatCurrency(liabilities);
                document.getElementById('total-revenue').textContent = this.formatCurrency(revenue);
                document.getElementById('total-expenses').textContent = this.formatCurrency(expenses);
            }

            // Add a new account
            addAccount(name, type, balance = 0) {
                const newAccount = {
                    account_id: this.currentId++,
                    account_name: name,
                    account_type: type,
                    balance: parseFloat(balance)
                };
                
                this.accounts.push(newAccount);
                this.saveAccounts();
                this.renderAccounts();
                this.calculateSummary();
                
                return newAccount;
            }

            // Edit an existing account
            editAccount(id, name, type, balance) {
                const account = this.accounts.find(a => a.account_id === id);
                if (account) {
                    account.account_name = name;
                    account.account_type = type;
                    account.balance = parseFloat(balance);
                    
                    this.saveAccounts();
                    this.renderAccounts();
                    this.calculateSummary();
                    
                    return account;
                }
                return null;
            }

            // Archive an account
            archiveAccount(id) {
                this.accounts = this.accounts.filter(a => a.account_id !== id);
                this.saveAccounts();
                this.renderAccounts();
                this.calculateSummary();
            }

            // Search accounts
            searchAccounts(query) {
                const filtered = this.accounts.filter(account => 
                    account.account_name.toLowerCase().includes(query.toLowerCase()) ||
                    account.account_type.toLowerCase().includes(query.toLowerCase()) ||
                    account.account_id.toString().includes(query)
                );
                
                this.renderFilteredAccounts(filtered);
            }

            // Render filtered accounts
            renderFilteredAccounts(filteredAccounts) {
                const tbody = document.querySelector('#accounts-table tbody');
                tbody.innerHTML = '';
                
                filteredAccounts.forEach(account => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${account.account_id}</td>
                        <td>${account.account_name}</td>
                        <td><span class="badge ${account.account_type.toLowerCase()}">${account.account_type}</span></td>
                        <td class="balance">${this.formatCurrency(account.balance)}</td>
                        <td>
                            <button class="btn-edit" data-id="${account.account_id}">Edit</button>
                            <button class="btn-archive" data-id="${account.account_id}">Archive</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }

            // Set up event listeners
            setupEventListeners() {
                // Add account button
                document.getElementById('add-account').addEventListener('click', () => {
                    const name = prompt('Enter account name:');
                    if (!name) return;
                    
                    const type = prompt('Enter account type (Asset, Liability, Revenue, Expense):');
                    if (!['Asset', 'Liability', 'Revenue', 'Expense'].includes(type)) {
                        alert('Invalid account type. Must be one of: Asset, Liability, Revenue, Expense');
                        return;
                    }
                    
                    const balance = prompt('Enter initial balance:') || '0';
                    if (isNaN(parseFloat(balance))) {
                        alert('Balance must be a number');
                        return;
                    }
                    
                    this.addAccount(name, type, balance);
                    alert('Account added successfully!');
                });

                // Edit and archive buttons (using event delegation)
                document.querySelector('#accounts-table tbody').addEventListener('click', (e) => {
                    if (e.target.classList.contains('btn-edit')) {
                        const id = parseInt(e.target.dataset.id);
                        const account = this.accounts.find(a => a.account_id === id);
                        
                        if (account) {
                            const name = prompt('Enter new account name:', account.account_name);
                            if (!name) return;
                            
                            const type = prompt('Enter new account type:', account.account_type);
                            if (!['Asset', 'Liability', 'Revenue', 'Expense'].includes(type)) {
                                alert('Invalid account type. Must be one of: Asset, Liability, Revenue, Expense');
                                return;
                            }
                            
                            const balance = prompt('Enter new balance:', account.balance);
                            if (isNaN(parseFloat(balance))) {
                                alert('Balance must be a number');
                                return;
                            }
                            
                            this.editAccount(id, name, type, balance);
                            alert('Account updated successfully!');
                        }
                    }
                    
                    if (e.target.classList.contains('btn-archive')) {
                        const id = parseInt(e.target.dataset.id);
                        if (confirm('Are you sure you want to archive this account?')) {
                            this.archiveAccount(id);
                            alert('Account archived successfully!');
                        }
                    }
                });

                // Search functionality
                document.getElementById('search').addEventListener('input', (e) => {
                    const query = e.target.value.trim();
                    if (query) {
                        this.searchAccounts(query);
                    } else {
                        this.renderAccounts();
                    }
                });

                // Search button
                document.querySelector('.search-btn').addEventListener('click', () => {
                    const query = document.getElementById('search').value.trim();
                    if (query) {
                        this.searchAccounts(query);
                    }
                });
            }
        }

        // Initialize the application when the DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            const accountManager = new JournalAccountManager();
        });
    </script>
</body>
</html>