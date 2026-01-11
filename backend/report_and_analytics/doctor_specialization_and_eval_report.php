<?php
include 'header.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Doctors</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        body {
            background: #fff;
            font-family: "Poppins", sans-serif;
        }

        .doctor-card {
            border: 1px solid #000;
            border-radius: 14px;
            background: #fff;
            transition: all 0.3s ease;
        }

        .doctor-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .doctor-avatar-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: transparent;
            border: 2px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-weight: 600;
            font-size: 1.2rem;
            text-transform: uppercase;
        }

        .btn-view {
            border: 1px solid #000;
            background: #000;
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background: #fff;
            color: #000;
        }

        .page-header {
            text-align: center;
            margin-top: 40px;
            margin-bottom: 30px;
        }

        .page-header h4 {
            font-weight: 700;
            color: #000;
        }

        .table thead {
            background: #000;
            color: #fff;
        }

        .modal-header {
            background: #000;
            color: #fff;
        }

        .list-group-item {
            border-color: #ddd;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 25px;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <?php
        include 'sidebar.php'
        ?>

        <!-- Main Content -->
        <div class="main w-100">
            <div class="container my-5">

                <div class="page-header">
                    <h4>Available Doctors</h4>
                    <p class="text-muted">Meet our active medical professionals.</p>
                </div>

                <!-- Doctor Grid -->
                <div class="grid-container" id="doctorList">
                    <p class="text-center text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="doctorModal" tabindex="-1" aria-labelledby="doctorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title" id="doctorModalLabel">Doctor Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-semibold">Professional Details</h6>
                    <ul id="profDetails" class="list-group mb-3"></ul>

                    <h6 class="fw-semibold">Educational Background</h6>
                    <ul id="eduDetails" class="list-group mb-3"></ul>

                    <h6 class="fw-semibold">License Information</h6>
                    <ul id="licenseDetails" class="list-group mb-3"></ul>

                    <h6 class="fw-semibold">Evaluation Records</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Rating</th>
                                    <th>Comments</th>
                                </tr>
                            </thead>
                            <tbody id="evalTable">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        const doctorList = document.getElementById("doctorList");

        async function loadDoctors() {
            try {
                const response = await fetch('https://bsis-03.keikaizen.xyz/employee/getDoctorsDetails');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const doctors = await response.json();

                doctorList.innerHTML = '';
                doctors.forEach(doc => {
                    const initials = ((doc.first_name || ' ')[0] + (doc.last_name || ' ')[0]).toUpperCase();
                    const col = document.createElement("div");
                    col.innerHTML = `
                        <div class="card doctor-card p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="doctor-avatar-placeholder me-3">${initials}</div>
                                <div class="doctor-info">
                                    <h6 class="mb-0">${doc.first_name || ''} ${doc.last_name || ''}</h6>
                                    <small class="text-muted">${doc.specialization || '—'}</small><br>
                                    <small>${doc.department || '—'}</small>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <button class="btn-view" onclick="viewDetails('${doc.employee_id}')">View Details</button>
                            </div>
                        </div>`;
                    doctorList.appendChild(col);
                });
            } catch (error) {
                console.error('Error fetching doctor data:', error);
                doctorList.innerHTML = `<p class="text-danger text-center">Failed to load doctor data.</p>`;
            }
        }

        async function viewDetails(id) {
            try {
                const response = await fetch(`https://bsis-03.keikaizen.xyz/employee/getDoctorDetailsAndEvaluation/${id}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const doc = await response.json();

                document.getElementById("doctorModalLabel").innerText = `${doc.role || 'Doctor'} — ${doc.specialization || ''}`;
                document.getElementById("profDetails").innerHTML = `
                    <li class="list-group-item"><strong>Department:</strong> ${doc.department || '—'}</li>
                    <li class="list-group-item"><strong>Specialization:</strong> ${doc.specialization || '—'}</li>
                    <li class="list-group-item"><strong>Role:</strong> ${doc.role || '—'}</li>
                    <li class="list-group-item"><strong>Employment Type:</strong> ${doc.employmentType || '—'}</li>`;
                document.getElementById("eduDetails").innerHTML = `
                    <li class="list-group-item"><strong>Educational Status:</strong> ${doc.educationalStatus || '—'}</li>
                    <li class="list-group-item"><strong>Degree Type:</strong> ${doc.degreeType || '—'}</li>
                    <li class="list-group-item"><strong>Medical School:</strong> ${doc.medicalSchool || '—'}</li>
                    <li class="list-group-item"><strong>Graduation Year:</strong> ${doc.graduationYear || '—'}</li>`;
                document.getElementById("licenseDetails").innerHTML = `
                    <li class="list-group-item"><strong>License Type:</strong> ${doc.licenseType || '—'}</li>
                    <li class="list-group-item"><strong>License Number:</strong> ${doc.licenseNumber || '—'}</li>
                    <li class="list-group-item"><strong>Issued Date:</strong> ${doc.licenseIssued || '—'}</li>
                    <li class="list-group-item"><strong>Expiry Date:</strong> ${doc.licenseExpiry || '—'}</li>`;

                const evalTable = document.getElementById("evalTable");
                evalTable.innerHTML = "";
                if (doc.evaluation_records && doc.evaluation_records.length > 0) {
                    doc.evaluation_records.forEach(e => {
                        evalTable.innerHTML += `
                            <tr>
                                <td>${e.date || '—'}</td>
                                <td>${e.score || '—'}</td>
                                <td>${e.rating || '—'}</td>
                                <td>${e.comments || '—'}</td>
                            </tr>`;
                    });
                } else {
                    evalTable.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No evaluations found</td></tr>`;
                }

                new bootstrap.Modal(document.getElementById("doctorModal")).show();

            } catch (error) {
                console.error('Error fetching doctor details:', error);
                alert('Failed to load doctor details.');
            }
        }

        loadDoctors();
    </script>
</body>

</html>