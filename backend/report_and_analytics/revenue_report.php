<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Revenue Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <style>
        body {
            background-color: #f0f8ff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .report-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 100, 0.1);
            overflow: hidden;
        }

        .report-header {
            background: linear-gradient(135deg, #1a75bc 0%, #0d47a1 100%);
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }

        .report-body {
            padding: 30px;
        }

        .revenue-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            color: white;
            margin: 20px 0;
        }

        .revenue-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 100, 0, 0.2);
        }

        .amount {
            font-weight: 700;
            font-size: 2.5rem;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
        }

        .card-title {
            font-size: 1.3rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .hospital-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .report-date {
            color: #666;
            font-style: italic;
            text-align: center;
            margin-top: 20px;
        }

        .year-selector {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 5px;
            padding: 8px 15px;
            font-size: 1rem;
            margin-top: 15px;
            width: 150px;
            text-align: center;
        }

        .year-selector option {
            color: #333;
        }

        .section-title {
            color: #1a75bc;
            border-bottom: 2px solid #1a75bc;
            padding-bottom: 10px;
            margin: 25px 0 15px 0;
            font-weight: 600;
        }

        .quarter-item {
            border-left: 4px solid #1a75bc;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s;
        }

        .quarter-item:hover {
            background-color: #e8f4ff;
            transform: translateX(5px);
        }

        .quarter-title {
            font-weight: 600;
            color: #1a75bc;
            margin-bottom: 5px;
        }

        .quarter-amount {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2E7D32;
        }

        .filter-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }

        .filter-label {
            color: white;
            margin-right: 10px;
            font-weight: 500;
        }

        .loading-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .error-message {
            color: #ff4d4d;
            background-color: #ffe6e6;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            display: none;
        }

        .data-loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .quarter-loading {
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quarter-loading::after {
            content: "Loading...";
            color: #1a75bc;
            font-style: italic;
        }

        /* UPDATED STYLES FOR BREAKDOWN MODAL */
        .breakdown-modal .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }

        .breakdown-modal .modal-header {
            background: linear-gradient(135deg, #1a75bc 0%, #0d47a1 100%);
            color: white;
            border-bottom: none;
        }

        .breakdown-modal .modal-title {
            font-weight: 600;
        }

        .breakdown-modal .close {
            color: white;
            opacity: 0.8;
        }

        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }

        .breakdown-table th {
            background-color: #f2f6fc;
            font-weight: 600;
            color: #2c3e50;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }

        .breakdown-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        .breakdown-table tr:hover {
            background-color: #f8f9fa;
        }

        .breakdown-amount {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }

        .breakdown-positive {
            color: #2e7d32;
        }

        .breakdown-loading {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }

        .breakdown-total {
            font-weight: 700;
            background-color: #e8f4ff;
        }

        /* Remove chart styles as they're not needed */
    </style>
</head>

<body>
    <div class="d-flex">
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
        <div class="report-container">
            <div class="report-header">
                <h1 class="display-5">HOSPITAL REVENUE REPORT</h1>
                <div class="filter-container">
                    <span class="filter-label"><i class="fas fa-calendar-alt me-2"></i>Select Year:</span>
                    <select class="year-selector" id="yearSelector">
                        <option value="">Loading years...</option>
                    </select>
                    <span id="loadingIndicator" class="loading-indicator" style="display:none;"></span>
                </div>
            </div>

            <div class="report-body text-center">
                <div id="errorMessage" class="error-message">
                    Error loading data. Please try again.
                </div>

                <div class="revenue-card" id="revenueCard">
                    <div class="card-body p-5">
                        <h5 class="card-title">TOTAL REVENUE</h5>
                        <p class="card-text amount" id="totalRevenue">$0</p>
                        <p class="report-date">For year: <span id="selectedYear">-</span></p>
                    </div>
                </div>

                <h3 class="section-title">QUARTERLY BREAKDOWN</h3>

                <div class="quarter-list">
                    <div class="quarter-item d-flex justify-content-between align-items-center" id="q1Item">
                        <div>
                            <div class="quarter-title">First Quarter</div>
                            <div class="quarter-period">Jan - Mar <span class="year-text">-</span></div>
                        </div>
                        <div class="quarter-amount" id="q1Revenue">$0</div>
                        <button class="btn btn-sm btn-outline-primary ms-2 view-breakdown" title="View breakdown" data-quarter="1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="quarter-item d-flex justify-content-between align-items-center" id="q2Item">
                        <div>
                            <div class="quarter-title">Second Quarter</div>
                            <div class="quarter-period">Apr - Jun <span class="year-text">-</span></div>
                        </div>
                        <div class="quarter-amount" id="q2Revenue">$0</div>
                        <button class="btn btn-sm btn-outline-primary ms-2 view-breakdown" title="View breakdown" data-quarter="2">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="quarter-item d-flex justify-content-between align-items-center" id="q3Item">
                        <div>
                            <div class="quarter-title">Third Quarter</div>
                            <div class="quarter-period">Jul - Sep <span class="year-text">-</span></div>
                        </div>
                        <div class="quarter-amount" id="q3Revenue">$0</div>
                        <button class="btn btn-sm btn-outline-primary ms-2 view-breakdown" title="View breakdown" data-quarter="3">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="quarter-item d-flex justify-content-between align-items-center" id="q4Item">
                        <div>
                            <div class="quarter-title">Fourth Quarter</div>
                            <div class="quarter-period">Oct - Dec <span class="year-text">-</span></div>
                        </div>
                        <div class="quarter-amount" id="q4Revenue">$0</div>
                        <button class="btn btn-sm btn-outline-primary ms-2 view-breakdown" title="View breakdown" data-quarter="4">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- UPDATED: Breakdown Modal -->
    <div class="modal fade breakdown-modal" id="breakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Quarter <span id="modalQuarter"></span> Revenue Breakdown - <span id="modalYear"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="breakdown-loading" id="breakdownLoading">
                        <i class="fas fa-spinner fa-spin me-2"></i>Loading breakdown data...
                    </div>
                    <div class="breakdown-content" id="breakdownContent" style="display: none;">
                        <div class="table-responsive">
                            <table class="breakdown-table">
                                <thead>
                                    <tr>
                                        <th>Report ID</th>
                                        <th>Year</th>
                                        <th>Month</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="breakdownTableBody">
                                    <!-- Data will be populated here -->
                                </tbody>
                                <tfoot>
                                    <tr class="breakdown-total">
                                        <td colspan="4">Total Revenue</td>
                                        <td class="breakdown-amount" id="breakdownTotal">$0</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const yearSelector = document.getElementById('yearSelector');
            const selectedYearSpan = document.getElementById('selectedYear');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const yearTextElements = document.querySelectorAll('.year-text');
            const totalRevenueElement = document.getElementById('totalRevenue');
            const errorMessage = document.getElementById('errorMessage');
            const revenueCard = document.getElementById('revenueCard');
            const q1Revenue = document.getElementById('q1Revenue');
            const q2Revenue = document.getElementById('q2Revenue');
            const q3Revenue = document.getElementById('q3Revenue');
            const q4Revenue = document.getElementById('q4Revenue');
            const q1Item = document.getElementById('q1Item');
            const q2Item = document.getElementById('q2Item');
            const q3Item = document.getElementById('q3Item');
            const q4Item = document.getElementById('q4Item');

            // Breakdown modal elements
            const breakdownModal = new bootstrap.Modal(document.getElementById('breakdownModal'));
            const modalQuarter = document.getElementById('modalQuarter');
            const modalYear = document.getElementById('modalYear');
            const breakdownLoading = document.getElementById('breakdownLoading');
            const breakdownContent = document.getElementById('breakdownContent');
            const breakdownTableBody = document.getElementById('breakdownTableBody');
            const breakdownTotal = document.getElementById('breakdownTotal');
            const viewBreakdownButtons = document.querySelectorAll('.view-breakdown');

            // Add event listeners to view breakdown buttons
            viewBreakdownButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const quarter = this.getAttribute('data-quarter');
                    const year = yearSelector.value;

                    // Only Q3 has detailed data available
                    if (quarter !== '3') {
                        alert('Detailed breakdown is only available for Q3 at this time.');
                        return;
                    }

                    showBreakdownModal(quarter, year);
                });
            });

            // Function to show breakdown modal
            function showBreakdownModal(quarter, year) {
                // Set modal title
                modalQuarter.textContent = quarter;
                modalYear.textContent = year;

                // Show loading state
                breakdownLoading.style.display = 'block';
                breakdownContent.style.display = 'none';

                // Show the modal
                breakdownModal.show();

                // Fetch data from the API endpoint
                fetch(`http://localhost:5288/journal/getQuarterThreeRevenueDetails/${year}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Render the breakdown data
                        renderBreakdownData(data);

                        // Hide loading, show content
                        breakdownLoading.style.display = 'none';
                        breakdownContent.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error fetching breakdown data:', error);
                        breakdownLoading.innerHTML = `
                            <div class="text-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Error loading breakdown data. Please try again.
                            </div>
                        `;
                    });
            }

            // Function to render breakdown data
            function renderBreakdownData(data) {
                // Format currency
                const formatter = new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // Calculate total
                let total = 0;
                if (Array.isArray(data)) {
                    total = data.reduce((sum, item) => sum + (item.amount || 0), 0);
                }

                // Update total
                breakdownTotal.textContent = formatter.format(total);

                // Clear table body
                breakdownTableBody.innerHTML = '';

                // Check if we have data
                if (!Array.isArray(data) || data.length === 0) {
                    breakdownTableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center">No data available</td>
                        </tr>
                    `;
                    return;
                }

                // Function to get month name from number
                function getMonthName(monthNumber) {
                    const months = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'
                    ];
                    return months[monthNumber - 1] || monthNumber;
                }

                // Add rows for each data item
                data.forEach(item => {
                    const row = document.createElement('tr');

                    row.innerHTML = `
                        <td>${item.report_id || '-'}</td>
                        <td>${item.year || '-'}</td>
                        <td>${getMonthName(item.month)}</td>
                        <td>${item.description || '-'}</td>
                        <td class="breakdown-amount breakdown-positive">${formatter.format(item.amount || 0)}</td>
                    `;

                    breakdownTableBody.appendChild(row);
                });
            }

            // Show loading indicator
            loadingIndicator.style.display = 'inline-block';

            // Fetch available years from the API endpoint
            fetch('http://localhost:5288/journal/availableYears')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(years => {
                    // Clear the default option
                    yearSelector.innerHTML = '';

                    // Check if we got an array of years
                    if (Array.isArray(years) && years.length > 0) {
                        // Sort years in descending order (most recent first)
                        years.sort((a, b) => b - a);

                        // Add each year as an option
                        years.forEach(year => {
                            const option = document.createElement('option');
                            option.value = year;
                            option.textContent = year;
                            yearSelector.appendChild(option);
                        });

                        // Set the default selected year to the most recent one
                        yearSelector.value = years[0];
                        selectedYearSpan.textContent = years[0];

                        // Update all year text elements on the page
                        updateYearTexts(years[0]);

                        // Fetch revenue data for the selected year
                        fetchRevenueData(years[0]);
                    } else {
                        // Fallback to hardcoded years if API returns empty or invalid data
                        populateFallbackYears();
                    }
                })
                .catch(error => {
                    console.error('Error fetching years:', error);
                    errorMessage.style.display = 'block';
                    errorMessage.textContent = 'Error loading available years. Using default years.';
                    // Use fallback years if the API call fails
                    populateFallbackYears();
                })
                .finally(() => {
                    // Hide loading indicator
                    loadingIndicator.style.display = 'none';
                });

            // Update the selected year text when user changes the dropdown
            yearSelector.addEventListener('change', function() {
                const selectedYear = this.value;
                selectedYearSpan.textContent = selectedYear;

                // Update all year text elements on the page
                updateYearTexts(selectedYear);

                // Fetch revenue data for the selected year
                fetchRevenueData(selectedYear);
            });

            function fetchRevenueData(year) {
                // Show loading state
                revenueCard.classList.add('data-loading');
                loadingIndicator.style.display = 'inline-block';
                errorMessage.style.display = 'none';

                // Reset quarterly data to loading state
                setQuarterLoadingState();

                // Fetch revenue data for the selected year
                fetch(`http://localhost:5288/journal/getYearRevenue/${year}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(revenueData => {
                        // Update the UI with the revenue data
                        updateRevenueData(revenueData);

                        // Fetch quarterly data
                        fetchQuarterlyData(year);
                    })
                    .catch(error => {
                        console.error('Error fetching revenue data:', error);
                        errorMessage.style.display = 'block';
                        errorMessage.textContent = 'Error loading revenue data. Please try again.';
                    })
                    .finally(() => {
                        // Hide loading state
                        revenueCard.classList.remove('data-loading');
                        loadingIndicator.style.display = 'none';
                    });
            }

            function fetchQuarterlyData(year) {
                // Array of quarters to fetch
                const quarters = [1, 2, 3, 4];

                // Fetch data for each quarter
                quarters.forEach(quarter => {
                    fetch(`http://localhost:5288/journal/getQuarterRevenues/${year}/${quarter}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`Network response for Q${quarter} was not ok`);
                            }
                            return response.json();
                        })
                        .then(quarterData => {
                            // Update the UI with the quarter data
                            updateQuarterData(quarter, quarterData);
                        })
                        .catch(error => {
                            console.error(`Error fetching Q${quarter} data:`, error);
                            // Set to 0 if there's an error
                            updateQuarterData(quarter, 0);
                        });
                });
            }

            function setQuarterLoadingState() {
                // Set all quarters to loading state
                [q1Item, q2Item, q3Item, q4Item].forEach(item => {
                    item.classList.add('quarter-loading');
                });

                // Set all quarter amounts to loading
                [q1Revenue, q2Revenue, q3Revenue, q4Revenue].forEach(element => {
                    element.textContent = 'Loading...';
                });
            }

            function updateQuarterData(quarter, data) {
                // Remove loading state from the quarter
                document.getElementById(`q${quarter}Item`).classList.remove('quarter-loading');

                // Format the revenue as currency
                const formatter = new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });

                // Update the quarter revenue
                let revenueValue = 0;

                if (typeof data === 'number') {
                    revenueValue = data;
                } else if (typeof data === 'object' && data.revenue !== undefined) {
                    revenueValue = data.revenue;
                } else if (typeof data === 'object' && data.total !== undefined) {
                    revenueValue = data.total;
                }

                document.getElementById(`q${quarter}Revenue`).textContent = formatter.format(revenueValue);
            }

            function updateRevenueData(data) {
                // Format the total revenue as currency
                const formatter = new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });

                // Update total revenue
                let totalRevenue = 0;

                if (typeof data === 'number') {
                    totalRevenue = data;
                } else if (typeof data === 'object' && data.totalRevenue !== undefined) {
                    totalRevenue = data.totalRevenue;
                } else if (typeof data === 'object' && data.total !== undefined) {
                    totalRevenue = data.total;
                }

                totalRevenueElement.textContent = formatter.format(totalRevenue);
            }

            function populateFallbackYears() {
                const fallbackYears = [2023, 2022, 2021, 2020, 2019];
                yearSelector.innerHTML = '';

                fallbackYears.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    yearSelector.appendChild(option);
                });

                yearSelector.value = 2023;
                selectedYearSpan.textContent = 2023;
                updateYearTexts(2023);

                // Fetch revenue data for the fallback year
                fetchRevenueData(2023);
            }

            function updateYearTexts(year) {
                yearTextElements.forEach(element => {
                    element.textContent = year;
                });
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>