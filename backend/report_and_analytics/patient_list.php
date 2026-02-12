<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records List</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background-color: #f7f7f7;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content-wrapper {
            padding: 30px;
            max-width: 1200px;
            margin: auto;
        }

        .patient-list-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
            overflow: hidden;
        }

        .header {
            background-color: #000;
            color: #fff;
            padding: 25px;
            text-align: center;
        }

        .stats-bar {
            display: flex;
            justify-content: space-around;
            padding: 15px;
            background: #f1f1f1;
            font-weight: 600;
        }

        .search-bar {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #fafafa;
        }

        .search-bar input {
            border-radius: 50px;
            padding: 10px 20px;
            width: 100%;
            border: 1px solid #ccc;
        }

        .table th {
            font-weight: 600;
        }

        .btn-view {
            background: #000;
            color: #fff;
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 0.9rem;
        }

        .btn-view:hover {
            background: #333;
        }

        .pagination-container {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            background-color: #fafafa;
            display: flex;
            justify-content: center;
        }

        /* Chart container */
        .chart-container {
            width: 300px;
            margin: 20px auto;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <?php
        include 'sidebar.php'
        ?>
        <div class="content-wrapper">
            <div class="patient-list-container">

                <!-- HEADER -->
                <div class="header">
                    <h2><i class="bi bi-person-lines-fill"></i> Patient Records List</h2>
                </div>

                <!-- STATS -->
                <div class="stats-bar">
                    <div>Average Age: <span id="avgAge">-</span></div>
                    <div>Male: <span id="maleCount">-</span></div>
                    <div>Female: <span id="femaleCount">-</span></div>
                </div>

                <!-- AGE DISTRIBUTION CHART -->
                <div class="chart-container">
                    <canvas id="ageChart"></canvas>
                </div>

                <!-- SEARCH -->
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search by patient name or ID..." onkeyup="filterPatients()">
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Full Name</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Contact</th>
                                <th style="width:120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="patientBody"></tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <div class="pagination-container">
                    <ul class="pagination mb-0" id="pagination"></ul>
                </div>

            </div>
        </div>

        <script>
            const apiBaseUrl = "https://localhost:7212/patient/patientDetails";
            let currentPage = 1;
            const pageSize = 5;
            let ageChart;

            async function fetchPatients(page = 1) {
                try {
                    const response = await fetch(`${apiBaseUrl}?page=${page}&size=${pageSize}`);
                    if (!response.ok) throw new Error("API error");

                    const data = await response.json();

                    document.getElementById("avgAge").innerText = data.averageAge ?? "-";
                    document.getElementById("maleCount").innerText = data.maleCount ?? "-";
                    document.getElementById("femaleCount").innerText = data.femaleCount ?? "-";

                    renderTable(data.patients);
                    setupPagination(data.patients.length);
                    renderAgeChart(data.ages); // <-- use the ages array now

                } catch (err) {
                    console.error(err);
                    document.getElementById("patientBody").innerHTML =
                        `<tr><td colspan="6" class="text-center text-danger">Failed to load data</td></tr>`;
                }
            }

            function renderTable(patients) {
                const body = document.getElementById("patientBody");
                body.innerHTML = "";

                if (!patients || patients.length === 0) {
                    body.innerHTML =
                        `<tr><td colspan="6" class="text-center text-muted">No records found</td></tr>`;
                    return;
                }

                patients.forEach(p => {
                    body.innerHTML += `
                <tr>
                    <td>${p.patientId}</td>
                    <td>${p.fullName}</td>
                    <td>${p.gender}</td>
                    <td>${p.age}</td>
                    <td>${p.contact}</td>
                    <td>
                        <button class="btn btn-view" onclick="viewMedicalRecord(${p.patientId})">View</button>
                    </td>
                </tr>
            `;
                });
            }

            function setupPagination(count) {
                const pagination = document.getElementById("pagination");
                pagination.innerHTML = "";

                const prevDisabled = currentPage === 1 ? "disabled" : "";
                const nextDisabled = count < pageSize ? "disabled" : "";

                pagination.innerHTML = `
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>
            </li>
            <li class="page-item active">
                <a class="page-link" href="#">${currentPage}</a>
            </li>
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>
            </li>
        `;
            }

            function changePage(page) {
                if (page < 1) return;
                currentPage = page;
                fetchPatients(page);
            }

            async function filterPatients() {
                const search = document.getElementById("searchInput").value.toLowerCase().trim();
                if (!search) {
                    fetchPatients(currentPage);
                    return;
                }

                const response = await fetch(`${apiBaseUrl}?page=1&size=1000`);
                const data = await response.json();

                const filtered = data.patients.filter(p =>
                    p.fullName.toLowerCase().includes(search) ||
                    p.patientId.toString().includes(search)
                );

                renderTable(filtered);
                renderAgeChart(data.ages); // <-- chart still uses full ages
                document.getElementById("pagination").innerHTML = "";
            }

            function viewMedicalRecord(patientId) {
                window.location.href =
                    `http://localhost:8080/backend/report_and_analytics/patient_records.php?patient_id=${patientId}`;
            }

            function renderAgeChart(agesArray) {
                const ageGroups = {
                    '0-10': 0,
                    '11-20': 0,
                    '21-30': 0,
                    '31-40': 0,
                    '41-50': 0,
                    '51+': 0
                };

                agesArray.forEach(a => {
                    const age = a.age;
                    if (age <= 10) ageGroups['0-10']++;
                    else if (age <= 20) ageGroups['11-20']++;
                    else if (age <= 30) ageGroups['21-30']++;
                    else if (age <= 40) ageGroups['31-40']++;
                    else if (age <= 50) ageGroups['41-50']++;
                    else ageGroups['51+']++;
                });

                const ctx = document.getElementById('ageChart').getContext('2d');
                if (ageChart) ageChart.destroy();

                ageChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(ageGroups),
                        datasets: [{
                            data: Object.values(ageGroups),
                            backgroundColor: [
                                '#FF6384',
                                '#36A2EB',
                                '#FFCE56',
                                '#4BC0C0',
                                '#9966FF',
                                '#FF9F40'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'Age Distribution'
                            }
                        }
                    }
                });
            }

            fetchPatients(currentPage);
        </script>
    </div>
</body>

</html>