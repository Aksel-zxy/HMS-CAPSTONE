<?php include 'header.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Medical Record Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background-color: #f7f7f7;
            color: #212121;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Sidebar fixed */
        .sidebar-wrapper {
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            background: #111;
            color: #fff;
            overflow-y: auto;
            z-index: 100;
        }

        /* Main content shifts to right of sidebar */
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .report-container {
            max-width: 900px;
            margin: 20px auto 40px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .report-header {
            background: #000;
            color: #fff;
            padding: 30px;
            text-align: center;
        }

        .report-header h2 {
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .patient-info {
            padding: 25px 40px 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .report-body {
            padding: 30px 40px;
        }

        .section-title {
            color: #000;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            text-transform: uppercase;
        }

        table {
            width: 100%;
        }

        th {
            background-color: #f4f4f4;
            color: #000;
            font-weight: 600;
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        tr:hover {
            background-color: #fafafa;
        }

        .no-records {
            text-align: center;
            color: #777;
            padding: 20px;
            font-style: italic;
        }

        .footer {
            background: #000;
            color: #fff;
            text-align: center;
            padding: 12px;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>


    <div class="main-content">
        <!-- Back Button -->
        <div class="container mt-4">
            <a href="patient_list.php" class="btn btn-outline-dark mb-3">
                <i class="bi bi-arrow-left"></i> Back to Patient List
            </a>
        </div>

        <!-- Report Content -->
        <div class="report-container">
            <div class="report-header">
                <h2><i class="bi bi-file-earmark-medical"></i> Patient Medical Record Report</h2>
            </div>

            <div class="patient-info" id="patientInfo">
                <h5>Patient Information</h5>
                <p><strong>ID:</strong> <span id="patientId">Loading...</span></p>
                <p><strong>Name:</strong> <span id="patientName">Loading...</span></p>
                <p><strong>Gender:</strong> <span id="patientGender">Loading...</span></p>
                <p><strong>Address:</strong> <span id="patientAddress">Loading...</span></p>
                <p><strong>Date Generated:</strong> <span id="dateGenerated"></span></p>
            </div>

            <div class="report-body">
                <h5 class="section-title">Previous Medical Records</h5>

                <table id="medicalTable">
                    <thead>
                        <tr>
                            <th>Record ID</th>
                            <th>Condition Name</th>
                            <th>Diagnosis Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody id="medicalRecordsBody">
                        <tr>
                            <td colspan="4" class="text-center text-muted">Loading records...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="footer">
                <p>© 2025 HealthCare Records | Confidential Medical Data</p>
            </div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const patientId = urlParams.get("patient_id");

        const cachedData = localStorage.getItem("selectedPatientRecord");

        async function loadPatientRecord() {
            try {
                let data;

                if (cachedData) {
                    data = JSON.parse(cachedData);
                } else {
                    const response = await fetch(`https://localhost:7212/employee/patientMedicalRecords/${patientId}`);
                    if (!response.ok) throw new Error("Failed to fetch patient record.");
                    data = await response.json();
                }

                document.getElementById("patientId").textContent = data.patientId;
                document.getElementById("patientName").textContent = data.fullName;
                document.getElementById("patientGender").textContent = data.gender;
                document.getElementById("patientAddress").textContent = data.address;

                const today = new Date();
                document.getElementById("dateGenerated").textContent = today.toLocaleDateString("en-US", {
                    year: "numeric",
                    month: "long",
                    day: "numeric"
                });

                const tbody = document.getElementById("medicalRecordsBody");
                tbody.innerHTML = "";

                if (!data.prevMedRecs || data.prevMedRecs.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="4" class="no-records">No previous medical records found.</td></tr>`;
                    return;
                }

                data.prevMedRecs.forEach(record => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td>${record.recordId}</td>
                        <td>${record.conditionName}</td>
                        <td>${new Date(record.diagnosisDate).toLocaleDateString()}</td>
                        <td>${record.notes}</td>
                    `;
                    tbody.appendChild(tr);
                });

            } catch (error) {
                console.error(error);
                document.getElementById("medicalRecordsBody").innerHTML = `
                    <tr><td colspan="4" class="text-danger text-center">Error loading data.</td></tr>`;
            }
        }

        loadPatientRecord();
    </script>

</body>

</html>