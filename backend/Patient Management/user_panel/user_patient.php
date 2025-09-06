<?php
include '../../../SQL/config.php';

if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'patient') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// Fetch user details from database
$query = "SELECT * FROM patient_user WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Patient Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="../assets/CSS/dashboard.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="user_patient.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
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
                        <a href="../Patient Management/registered.php" class="sidebar-link">Registered Patient</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Patient Management/inpatient.php" class="sidebar-link">Inpatients</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Patient Management/outpatient.php" class="sidebar-link">Outpatients</a>
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
                    <span style="font-size: 18px;">Bedding</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link" data-bs-toggle="#" data-bs-target="#" aria-expanded="false"
                    aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-solid fa-clock-rotate-left" viewBox="0 0 16 16">

                        <path
                            d="M320 128C426 128 512 214 512 320C512 426 426 512 320 512C254.8 512 197.1 479.5 162.4 429.7C152.3 415.2 132.3 411.7 117.8 421.8C103.3 431.9 99.8 451.9 109.9 466.4C156.1 532.6 233 576 320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C234.3 64 158.5 106.1 112 170.7L112 144C112 126.3 97.7 112 80 112C62.3 112 48 126.3 48 144L48 256C48 273.7 62.3 288 80 288L104.6 288C105.1 288 105.6 288 106.1 288L192.1 288C209.8 288 224.1 273.7 224.1 256C224.1 238.3 209.8 224 192.1 224L153.8 224C186.9 166.6 249 128 320 128zM344 216C344 202.7 333.3 192 320 192C306.7 192 296 202.7 296 216L296 320C296 326.4 298.5 332.5 303 337L375 409C384.4 418.4 399.6 418.4 408.9 409C418.2 399.6 418.3 384.4 408.9 375.1L343.9 310.1L343.9 216z" />
                    </svg>
                    <span style="font-size: 18px;">Treatment History</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link" data-bs-toggle="#" data-bs-target="#" aria-expanded="false"
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
                    <span style="font-size: 18px;">Summary</span>
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
            <div class="container-fluid border container-sm rounded shadow-sm bg-white p-4 mx-auto">
                <div class="border-bottom border-2 pb-3">
                    <h3>Records</h3>
                </div>
                <div class="mt-4 border-3 border-bottom">
                    <p class="fs-6"><strong>Patient ID:</strong></p>
                    <p class="fs-6"><strong>Name:</strong></p>
                    <p class="fs-6"><strong>Age:</strong></p>
                    <p class="fs-6"><strong>Contact:</strong></p>
                    <p class="fs-6"><strong>Gender:</strong></p>

                </div>

                <div class="mt-4 border-3 border-bottom">
                    <table
                        style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif; font-size:14px; background:#fff; border-radius:8px; overflow:hidden; min-height:200px;">
                        <thead>
                            <tr style="background:#f1f5f9; border-bottom:2px solid #dee2e6; text-align:left;">
                                <th style="padding:12px; text-align:center;">Patient ID</th>
                                <th style="padding:12px; text-align:center;">First Name</th>
                                <th style="padding:12px; text-align:center;">Middle Name</th>
                                <th style="padding:12px; text-align:center;">Last Name</th>
                                <th style="padding:12px; text-align:center;"></th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($patients->num_rows > 0): ?>
                            <?php while($row = $patients->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid #f1f1f1; transition:background 0.2s;"
                                onmouseover="this.style.background='#f9fbfd';" onmouseout="this.style.background='';">
                                <td style="padding:12px; text-align:center;"><?= htmlspecialchars($row['patient_id']) ?>
                                </td>
                                <td style="padding:12px; text-align:center;"><?= htmlspecialchars($row['fname']) ?></td>
                                <td style="padding:12px; text-align:center;"><?= htmlspecialchars($row['mname']) ?></td>


                                <td style="text-align:center;">
                                    <a class="btn btn-sm"
                                        href="../Patient Management/discharged.php?patient_id=<?= $row['patient_id'] ?>"
                                        style="padding:6px 12px; border-radius:6px; font-size:13px; background:red; border:none; color:#fff; cursor:pointer;">
                                        Download
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="11"
                                    style="text-align:center; padding:40px; color:#6c757d; font-style:italic;">
                                    📋 No Patients Found
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 border-3 border-bottom">
                    <table
                        style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif; font-size:14px; background:#fff; border-radius:8px; overflow:hidden; min-height:200px;">
                        <thead>
                            <tr style="background:#f1f5f9; border-bottom:2px solid #dee2e6; text-align:left;">
                                <th style="padding:12px; text-align:center;">Patient ID</th>
                                <th style="padding:12px; text-align:center;">First Name</th>
                                <th style="padding:12px; text-align:center;">Middle Name</th>
                                <th style="padding:12px; text-align:center;">Last Name</th>
                                <th style="padding:12px; text-align:center;"></th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($patients->num_rows > 0): ?>
                            <?php while($row = $patients->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid #f1f1f1; transition:background 0.2s;"
                                onmouseover="this.style.background='#f9fbfd';" onmouseout="this.style.background='';">
                                <td style="padding:12px; text-align:center;"><?= htmlspecialchars($row['patient_id']) ?>
                                </td>
                                <td style="padding:12px; text-align:center;"><?= htmlspecialchars($row['fname']) ?></td>
                                <td style="padding:12px; text-align:center;"><?= htmlspecialchars($row['mname']) ?></td>


                                <td style="text-align:center;">
                                    <a class="btn btn-sm"
                                        href="../Patient Management/discharged.php?patient_id=<?= $row['patient_id'] ?>"
                                        style="padding:6px 12px; border-radius:6px; font-size:13px; background:red; border:none; color:#fff; cursor:pointer;">
                                        Download
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="11"
                                    style="text-align:center; padding:40px; color:#6c757d; font-style:italic;">
                                    📋 No Patients Found
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>


            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>

    <script>
    const toggler = document.querySelector(".toggler-btn");
    toggler.addEventListener("click", function() {
        document.querySelector("#sidebar").classList.toggle("collapsed");
    });
    </script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>