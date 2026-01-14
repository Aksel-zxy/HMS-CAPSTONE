<?php
include '../../SQL/config.php';
include 'class/dashb.php';
include 'class/logs.php';

$callerObj = new Dashboard($conn);
$beds= $callerObj->getAvailableBedsCount($conn);
$appointments = $callerObj->getAppointmentsCount($conn);
$outpatients = $callerObj->getOutpatientsCount($conn);
$inpatients = $callerObj->getInpatientsCount($conn);
$registered = $callerObj->getRegisteredPatientsCount($conn);
$totalPatients = $callerObj->getTotalPatients($conn);




if (!isset($_SESSION['patient']) || $_SESSION['patient'] !== true) {
header('Location: login.php'); // Redirect to login if not logged in
exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
echo "User ID is not set in session.";
exit();
}

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
echo "No user found.";
exit();
}

logAction($conn, $_SESSION['user_id'], 'VIEW_DASHBOARD');


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Patient Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/dashboard.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="patient_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-cast" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse"
                    data-bs-target="#gerald" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-person-vcard" viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Patient Lists</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../patient_management/registered.php" class="sidebar-link">Registered Patient</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../patient_management/inpatient.php" class="sidebar-link">Inpatients</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../patient_management/outpatient.php" class="sidebar-link">Outpatients</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="appointment.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-regular fa-calendar" viewBox="0 0 16 16">

                        <path d=" M216 64C229.3 64 240 74.7 240 88L240 128L400 128L400 88C400 74.7 410.7 64 424 64C437.3
                        64 448 74.7 448 88L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480
                        544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 88C192 74.7
                        202.7 64 216 64zM216 176L160 176C151.2 176 144 183.2 144 192L144 240L496 240L496 192C496 183.2
                        488.8 176 480 176L216 176zM144 288L144 480C144 488.8 151.2 496 160 496L480 496C488.8 496 496
                        488.8 496 480L496 288L144 288z" />
                    </svg>
                    <span style="font-size: 18px;">Appointment</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="bedding.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#" aria-expanded="false"
                    aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16"
                        fill="currentColor" class="fa-solid fa-bed">
                        <!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path
                            d="M64 96C81.7 96 96 110.3 96 128L96 352L320 352L320 224C320 206.3 334.3 192 352 192L512 192C565 192 608 235 608 288L608 512C608 529.7 593.7 544 576 544C558.3 544 544 529.7 544 512L544 448L96 448L96 512C96 529.7 81.7 544 64 544C46.3 544 32 529.7 32 512L32 128C32 110.3 46.3 96 64 96zM144 256C144 220.7 172.7 192 208 192C243.3 192 272 220.7 272 256C272 291.3 243.3 320 208 320C172.7 320 144 291.3 144 256z" />
                    </svg>
                    <span style="font-size: 18px;">Bedding & Linen</span>
                </a>
            </li>


            <li class="sidebar-item">
                <a href="logs.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#" aria-expanded="false"
                    aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-regular fa-folder-closed" viewBox="0 0 16 16">

                        <path d=" M512 464L128 464C119.2 464 112 456.8 112 448L112 304L528 304L528 448C528 456.8 520.8
                        464 512 464zM528 256L112 256L112 160C112 151.2 119.2 144 128 144L266.7 144C270.2 144 273.5 145.1
                        276.3 147.2L314.7 176C328.5 186.4 345.4 192 362.7 192L512 192C520.8 192 528 199.2 528 208L528
                        256zM128 512L512 512C547.3 512 576 483.3 576 448L576 208C576 172.7 547.3 144 512 144L362.7
                        144C355.8 144 349 141.8 343.5 137.6L305.1 108.8C294 100.5 280.5 96 266.7 96L128 96C92.7 96 64
                        124.7 64 160L64 448C64 483.3 92.7 512 128 512z" />
                    </svg>
                    <span style="font-size: 18px;">Logs</span>
                </a>
            </li>
        </aside>
        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor"
                            class="bi bi-list-ul" viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?>
                            <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton"
                            style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong
                                        style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../logout.php"
                                    style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>

            <!-- START CODING HERE -->
            <div class="container-fluid mt-4">
                <div class="container text-black allign-items-center rounded welcome my-4">
                    <span class="username ml-4 me-4 fs-4 justify-content-end">Hello,
                        <?php echo $user['fname']; ?> <?php echo $user['lname']; ?>! Here's today's Hospital overview
                    </span>
                </div>

                <div class="row g-3 flex-nowrap overflow-auto justify-content-center">

                    <div class="col-10 col-sm-6 col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Inpatients</h6>
                                <p class="card-text fs-4"><?php echo $inpatients; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-10 col-sm-6 col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Outpatients</h6>
                                <p class="card-text fs-4"><?php echo $outpatients; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-10 col-sm-6 col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Total Patients</h6>
                                <p class="card-text fs-4"><?php echo $totalPatients; ?></p>
                            </div>
                        </div>
                    </div>


                    <div class="col-10 col-sm-6 col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Available Beds</h6>
                                <p class="card-text fs-4"><?php echo $beds; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-10 col-sm-6 col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Today's Appointments</h6>
                                <p class="card-text fs-4"><?php echo $appointments; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="container-fluid mt-4">
                <div class="d-flex flex-ROW gap-3">
                    <!-- Flex column wrapper -->
                    <div class="card w-100">
                        <div class="card-body justify-content-center">
                            <div class="d-flex gap-3 mb-3 align-items-end">
                                <div class="flex-grow-1">
                                    <label for="typeSelect" class="form-label">Data Type</label>
                                    <select id="typeSelect" class="form-select">
                                        <option value="inpatient">Inpatient</option>
                                        <option value="outpatient">Outpatient</option>
                                        <option value="appointments">Appointments</option>
                                        <option value="total">Total Patients</option>
                                    </select>
                                </div>

                                <div class="flex-grow-1">
                                    <label for="rangeSelect" class="form-label">Time Range</label>
                                    <select id="rangeSelect" class="form-select">
                                        <option value="monthly">Monthly</option>
                                        <option value="weekly">Weekly</option>
                                    </select>
                                </div>
                            </div>



                            <!-- Chart below -->
                            <div style="flex: 1; min-height: 400px;">
                                <canvas id="monthlyReportChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card w-25">
                        <div class="card-body">
                            <h5 class="card-title text-center pb-3">Quick Actions</h5>

                            <input type="text" id="patientSearch" class="form-control mb-3"
                                placeholder="Search patient...">
                            <div id="searchResults" class="list-group position-absolute" style="z-index:1000;"></div>


                            <div class="d-flex justify-content-evenly mb-2">
                                <button type="button" class="btn btn-primary btn-l rounded-3" id="Boton"
                                    data-bs-toggle="modal" data-bs-target="#addPatientModal" style="fontsize: 50px;">
                                    <span class="fs-5"><i class="fa-solid fa-plus me-2"></i> Add Patient</span>
                                </button>

                            </div>
                            <?php include 'icreate.php'; // This includes the modal code ?>

                            <div class="container mx-1 justify-content-evenly d-flex">
                                <button type="button" class="btn btn-primary btn-l rounded-3" data-bs-toggle="modal"
                                    data-bs-target="#moveModal">
                                    <span class="fs-5"><i class="fa-solid fa-arrows-up-down-left-right me-2"></i>
                                        Move Patient</span>
                                </button>
                            </div>
                            <?php include 'move.php'; ?>

                            <div class="container mt-3 mb-3 justify-content-evenly d-flex">
                                <button type="button" class="btn btn-primary btn-l rounded-3" data-bs-toggle="modal"
                                    data-bs-target="#appointmentModal">
                                    <span class="fs-5"><i class="fa-solid fa-plus me-2"></i> Add Appointment</span>
                                </button>
                            </div>
                            <?php include 'pcreate.php'; ?>


                        </div>




                    </div>
                </div>
            </div>
        </div>





        <!-- END CODING HERE -->
    </div>
    <!----- End of Main Content ----->
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <script>
    const toggler = document.querySelector(".toggler-btn");
    toggler.addEventListener("click", function() {
        document.querySelector("#sidebar").classList.toggle("collapsed");
    });

    const ctx = document.getElementById('monthlyReportChart').getContext('2d');

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [], // will be updated dynamically
            datasets: [{
                label: '',
                data: [],
                tension: 0.3,
                borderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    function loadChartData(type, range) {
        fetch(`class/dashboard_chart.php?type=${type}&range=${range}`)
            .then(res => res.json())
            .then(data => {
                chart.data.datasets[0].data = data;

                if (range === 'weekly') {
                    const labels = [];
                    for (let i = 6; i >= 0; i--) {
                        const d = new Date();
                        d.setDate(d.getDate() - i);
                        labels.push(d.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric'
                        }));
                    }
                    chart.data.labels = labels;
                } else {
                    chart.data.labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct',
                        'Nov', 'Dec'
                    ];
                }

                chart.data.datasets[0].label =
                    `${document.querySelector(`#typeSelect option[value="${type}"]`).text} (${range})`;

                chart.update();
            })
            .catch(err => console.error('Chart fetch error:', err));
    }

    // INITIAL LOAD
    loadChartData('inpatient', 'monthly');

    // Event listeners
    document.getElementById('typeSelect').addEventListener('change', () => {
        const type = document.getElementById('typeSelect').value;
        const range = document.getElementById('rangeSelect').value;
        loadChartData(type, range);
    });

    document.getElementById('rangeSelect').addEventListener('change', () => {
        const type = document.getElementById('typeSelect').value;
        const range = document.getElementById('rangeSelect').value;
        loadChartData(type, range);
    });




    // Search Patient
    const searchInput = document.getElementById('patientSearch');
    const resultsDiv = document.getElementById('searchResults');

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        if (query.length === 0) {
            resultsDiv.innerHTML = '';
            return;
        }

        fetch('class/search_patient.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                resultsDiv.innerHTML = '';

                if (data.length > 0) {
                    data.forEach(patient => {
                        const item = document.createElement('a');
                        item.href = `iview.php?patient_id=${patient.id}`;
                        item.classList.add('list-group-item', 'list-group-item-action');
                        item.textContent = patient.name;
                        resultsDiv.appendChild(item);
                    });
                } else {
                    const noItem = document.createElement('div');
                    noItem.classList.add('list-group-item', 'text-muted');
                    noItem.textContent = 'No results found';
                    resultsDiv.appendChild(noItem);
                }
            })
            .catch(err => {
                console.error('Error fetching patients:', err);
            });
    });

    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.innerHTML = '';
        }
    });
    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>

</body>

</html>