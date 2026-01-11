<?php include 'header.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background-color: #f7f7f7;
            color: #212121;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        .main-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Sidebar styling */
        .sidebar-wrapper {
            width: 250px;
            background: #111;
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            border-right: 1px solid #222;
            scrollbar-width: thin;
            scrollbar-color: #444 #111;
        }

        /* Sidebar scrollbar (for Chrome) */
        .sidebar-wrapper::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-wrapper::-webkit-scrollbar-thumb {
            background-color: #444;
            border-radius: 10px;
        }

        .sidebar-wrapper::-webkit-scrollbar-track {
            background-color: #111;
        }

        /* Fix background blending */
        .sidebar-wrapper * {
            background-color: transparent;
        }

        /* Main content area */
        .content-wrapper {
            margin-left: 250px;
            flex-grow: 1;
            padding: 30px;
            background-color: #f7f7f7;
            min-height: 100vh;
        }

        .patient-list-container {
            width: 100%;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .header {
            background-color: #000;
            color: #fff;
            text-align: center;
            padding: 25px;
        }

        .header h2 {
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .search-bar {
            padding: 20px 30px;
            background-color: #fafafa;
            border-bottom: 1px solid #e0e0e0;
        }

        .search-bar input {
            border-radius: 50px;
            padding: 10px 20px;
            border: 1px solid #ccc;
            width: 100%;
            outline: none;
        }

        .table thead {
            background-color: #f4f4f4;
        }

        .table th {
            color: #000;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }

        .table td {
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }

        .btn-view {
            background-color: #000;
            color: #fff;
            border-radius: 25px;
            padding: 6px 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background-color: #333;
            transform: translateY(-2px);
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background-color: #fafafa;
            border-top: 1px solid #e0e0e0;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar-wrapper {
                position: relative;
                width: 100%;
                height: auto;
                border-right: none;
            }

            .content-wrapper {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="sidebar-wrapper">
            <?php include 'sidebar.php' ?>
        </div>

        <div class="content-wrapper">
            <div class="patient-list-container">
                <div class="header">
                    <h2><i class="bi bi-person-lines-fill"></i> Patient Records List</h2>
                </div>

                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search patient by name or ID..." onkeyup="filterPatients()">
                </div>

                <div class="table-responsive">
                    <table class="table mb-0" id="patientTable">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Full Name</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Contact</th>
                                <th style="width: 130px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="patientBody"></tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <nav>
                        <ul class="pagination mb-0" id="pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <script>
        const apiBaseUrl = "https://bsis-03.keikaizen.xyz/patient/patientDetails";
        let currentPage = 1;
        const rowsPerPage = 10;
        let totalRecords = 0;

        async function fetchPatients(page = 1) {
            try {
                const response = await fetch(`${apiBaseUrl}/${page}/${rowsPerPage}`);
                if (!response.ok) throw new Error("Failed to fetch patient data");

                const data = await response.json();
                totalRecords = data.length;

                renderTable(data);
                setupPagination();
            } catch (error) {
                console.error(error);
                document.getElementById("patientBody").innerHTML =
                    `<tr><td colspan="6" class="text-center text-danger">Failed to load data</td></tr>`;
            }
        }

        function renderTable(patients) {
            const tableBody = document.getElementById("patientBody");
            tableBody.innerHTML = "";

            if (patients.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No patient records found.</td></tr>`;
                return;
            }

            patients.forEach(p => {
                const row = `
                    <tr>
                        <td>${p.patientId}</td>
                        <td>${p.fullName}</td>
                        <td>${p.gender}</td>
                        <td>${p.age}</td>
                        <td>${p.contact}</td>
                        <td>
                            <button class="btn btn-view" onclick="viewMedicalRecord(${p.patientId})">View</button>
                        </td>
                    </tr>`;
                tableBody.insertAdjacentHTML("beforeend", row);
            });
        }

        async function viewMedicalRecord(patientId) {
            try {
                const response = await fetch(`http://localhost:5288/employee/patientMedicalRecords/${patientId}`);
                if (!response.ok) throw new Error("Failed to fetch medical record data");

                const patientData = await response.json();
                localStorage.setItem("selectedPatientRecord", JSON.stringify(patientData));

                window.location.href = `http://localhost:8080/backend/report_and_analytics/patient_records.php?patient_id=${patientId}`;
            } catch (error) {
                console.error(error);
                alert("Unable to load patient record.");
            }
        }

        function setupPagination() {
            const pagination = document.getElementById("pagination");
            pagination.innerHTML = "";

            const prevDisabled = currentPage === 1 ? "disabled" : "";
            const nextDisabled = totalRecords < rowsPerPage ? "disabled" : "";

            pagination.innerHTML = `
                <li class="page-item ${prevDisabled}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>
                </li>
                <li class="page-item active"><a class="page-link" href="#">${currentPage}</a></li>
                <li class="page-item ${nextDisabled}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>
                </li>`;
        }

        function changePage(page) {
            if (page < 1) return;
            currentPage = page;
            fetchPatients(page);
        }

        async function filterPatients() {
            const search = document.getElementById("searchInput").value.trim().toLowerCase();
            if (!search) {
                fetchPatients(currentPage);
                return;
            }

            try {
                const response = await fetch(`${apiBaseUrl}/1/1000`);
                const allPatients = await response.json();
                const filtered = allPatients.filter(p =>
                    p.fullName.toLowerCase().includes(search) ||
                    p.patientId.toString().includes(search)
                );
                renderTable(filtered);
                document.getElementById("pagination").innerHTML = "";
            } catch (error) {
                console.error(error);
            }
        }

        fetchPatients(currentPage);
    </script>
</body>

</html>