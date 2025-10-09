<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Doctors Evaluation Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #eef2f7, #ffffff);
            font-family: 'Segoe UI', sans-serif;
            color: #2c3e50;
            min-height: 100vh;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .page-header h2 {
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1rem;
        }

        .doctor-card {
            border: none;
            border-radius: 1rem;
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.2);
        }

        /* Blank circular avatar placeholder */
        .doctor-avatar-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #e9ecef;
            border: 3px solid #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-weight: 600;
            font-size: 1.2rem;
            text-transform: uppercase;
        }

        .doctor-info h5 {
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .doctor-info p {
            margin: 0;
            color: #6c757d;
        }

        .btn-view {
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.9rem;
            background: #0d6efd;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-view:hover {
            background: #084298;
        }

        .modal-header {
            background: #0d6efd;
            color: #fff;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
        }

        .list-group-item {
            border: none;
            border-bottom: 1px solid #f1f1f1;
        }

        .table thead {
            background-color: #0d6efd;
            color: #fff;
        }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="page-header">
            <h2>Doctors Evaluation Dashboard</h2>
            <p>View professional details and performance evaluations of all active doctors.</p>
        </div>

        <div class="row g-4" id="doctorList">
            <!-- Doctor Cards Generated Here -->
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="doctorModal" tabindex="-1" aria-labelledby="doctorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title" id="doctorModalLabel">Doctor Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-secondary mb-3 fw-semibold">Professional Details</h6>
                    <ul id="profDetails" class="list-group mb-4 rounded-3 shadow-sm"></ul>

                    <h6 class="text-secondary mb-3 fw-semibold">Evaluation Records</h6>
                    <div class="table-responsive shadow-sm rounded-3">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Rating</th>
                                    <th>Comments</th>
                                </tr>
                            </thead>
                            <tbody id="evalTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Mock doctor data
        const doctors = [{
                employee_id: "DOC001",
                first_name: "Juan",
                last_name: "Dela Cruz",
                department: "Cardiology",
                specialization: "Cardiac Care",
                position_title: "Resident Doctor",
                shift_type: "Day",
                employment_type: "Full-time",
                evaluations: [{
                        date: "2025-09-15",
                        score: 89,
                        rating: "Excellent",
                        comments: "Great leadership and precision."
                    },
                    {
                        date: "2025-06-02",
                        score: 85,
                        rating: "Very Good",
                        comments: "Consistent and reliable."
                    }
                ]
            },
            {
                employee_id: "DOC002",
                first_name: "Maria",
                last_name: "Santos",
                department: "Pediatrics",
                specialization: "Child Health",
                position_title: "Consultant",
                shift_type: "Day",
                employment_type: "Part-time",
                evaluations: [{
                    date: "2025-08-10",
                    score: 78,
                    rating: "Good",
                    comments: "Kind and patient with clients."
                }]
            },
            {
                employee_id: "DOC003",
                first_name: "Ramon",
                last_name: "Garcia",
                department: "Surgery",
                specialization: "Orthopedic Surgery",
                position_title: "Head Surgeon",
                shift_type: "Night",
                employment_type: "Full-time",
                evaluations: [{
                    date: "2025-07-21",
                    score: 92,
                    rating: "Outstanding",
                    comments: "Exceptional surgical skills."
                }]
            }
        ];

        // Generate Doctor Cards
        const doctorList = document.getElementById("doctorList");
        doctors.forEach(doc => {
            const initials = (doc.first_name.charAt(0) + doc.last_name.charAt(0)).toUpperCase();
            const col = document.createElement("div");
            col.className = "col-md-4 col-sm-6";
            col.innerHTML = `
        <div class="card doctor-card p-3 h-100">
          <div class="d-flex align-items-center">
            <div class="doctor-avatar-placeholder me-3">${initials}</div>
            <div class="doctor-info">
              <h5>${doc.first_name} ${doc.last_name}</h5>
              <p>${doc.specialization}</p>
              <small class="text-muted">${doc.department}</small>
            </div>
          </div>
          <div class="mt-3 text-end">
            <button class="btn btn-view" onclick="viewDetails('${doc.employee_id}')">View Details</button>
          </div>
        </div>
      `;
            doctorList.appendChild(col);
        });

        // View Details Modal
        function viewDetails(id) {
            const doc = doctors.find(d => d.employee_id === id);
            if (!doc) return;

            document.getElementById("doctorModalLabel").innerText = `${doc.first_name} ${doc.last_name} — Details`;

            document.getElementById("profDetails").innerHTML = `
        <li class="list-group-item"><strong>Department:</strong> ${doc.department}</li>
        <li class="list-group-item"><strong>Specialization:</strong> ${doc.specialization}</li>
        <li class="list-group-item"><strong>Position Title:</strong> ${doc.position_title}</li>
        <li class="list-group-item"><strong>Shift Type:</strong> ${doc.shift_type}</li>
        <li class="list-group-item"><strong>Employment Type:</strong> ${doc.employment_type}</li>
      `;

            const evalTable = document.getElementById("evalTable");
            evalTable.innerHTML = "";
            if (doc.evaluations.length > 0) {
                doc.evaluations.forEach(e => {
                    evalTable.innerHTML += `
            <tr>
              <td>${e.date}</td>
              <td>${e.score}</td>
              <td>${e.rating}</td>
              <td>${e.comments}</td>
            </tr>
          `;
                });
            } else {
                evalTable.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No evaluations found</td></tr>`;
            }

            new bootstrap.Modal(document.getElementById("doctorModal")).show();
        }
    </script>

</body>

</html>