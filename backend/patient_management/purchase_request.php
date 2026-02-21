<?php
include '../../SQL/config.php';
include 'class/logs.php';


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



$user_id = $_SESSION['user_id'];

// 👤 Fetch user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("User not found.");

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department = $user['department'] ?? 'Unknown Department';
$department_id = $user['department_id'] ?? 0;
$request_date = date('F d, Y');

// 📤 Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = $_POST['items'] ?? [];
        $valid_items = array_filter($items, fn($i) => !empty(trim($i['name'] ?? '')));
        if (count($valid_items) === 0) throw new Exception("Please add at least one item before submitting.");

        // Remove 'items' from the query
        $stmt = $pdo->prepare("
            INSERT INTO department_request
            (user_id, department, department_id, month, total_items, status)
            VALUES
            (:user_id, :department, :department_id, :month, :total_items, 'Pending')
        ");
        $stmt->execute([
            ':user_id'       => $user_id,
            ':department'    => $department,
            ':department_id' => $department_id,
            ':month'         => date('Y-m-d'),
            ':total_items'   => count($valid_items)
        ]);

        $success = "Purchase request successfully submitted!";
        logAction($conn, $_SESSION['user_id'], 'USER_REQUESTED_PURCHASE');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}


// 🔎 Fetch user's requests
$request_stmt = $pdo->prepare("SELECT * FROM department_request WHERE user_id = ? ORDER BY created_at DESC");
$request_stmt->execute([$user_id]);
$my_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <style>
    body {
        background: #f8fafc;
    }

    .card {
        border-radius: 12px;
        margin-bottom: 30px;
    }

    .table {
        table-layout: fixed;
    }

    th,
    td {
        vertical-align: middle;
        text-align: center;
    }

    .unit-select {
        min-width: 120px;
    }

    .qty-input {
        min-width: 80px;
    }

    .pcs-box-input {
        min-width: 90px;
    }

    .total-pcs-input {
        background: #f8fafc;
        min-width: 90px;
    }

    .info-box strong {
        display: inline-block;
        width: 120px;
    }

    .status-badge {
        font-size: 0.85rem;
        padding: 0.4em 0.6em;
    }
    </style>
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

            <li class="sidebar-item">
                <a href="purchase_request.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-regular fa-folder-closed" viewBox="0 0 16 16">

                        <path d=" M512 464L128 464C119.2 464 112 456.8 112 448L112 304L528 304L528 448C528 456.8 520.8
                        464 512 464zM528 256L112 256L112 160C112 151.2 119.2 144 128 144L266.7 144C270.2 144 273.5 145.1
                        276.3 147.2L314.7 176C328.5 186.4 345.4 192 362.7 192L512 192C520.8 192 528 199.2 528 208L528
                        256zM128 512L512 512C547.3 512 576 483.3 576 448L576 208C576 172.7 547.3 144 512 144L362.7
                        144C355.8 144 349 141.8 343.5 137.6L305.1 108.8C294 100.5 280.5 96 266.7 96L128 96C92.7 96 64
                        124.7 64 160L64 448C64 483.3 92.7 512 128 512z" />
                    </svg>
                    <span style="font-size: 18px;">Purchase Request</span>
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

                <div class="container py-5">
                    <h2 class="mb-4 fw-bold">📋 Purchase Requests</h2>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="requestTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="form-tab" data-bs-toggle="tab" data-bs-target="#form"
                                type="button" role="tab">Request Form</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="my-requests-tab" data-bs-toggle="tab"
                                data-bs-target="#my-requests" type="button" role="tab">My Requests</button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3">
                        <!-- Request Form Tab -->
                        <div class="tab-pane fade show active" id="form" role="tabpanel">
                            <div class="card p-4">
                                <?php if(isset($success)) echo '<div class="alert alert-success">'.$success.'</div>'; ?>
                                <?php if(isset($error)) echo '<div class="alert alert-danger">'.$error.'</div>'; ?>

                                <div class="alert alert-info info-box mb-4">
                                    <div><strong>Department:</strong> <?= htmlspecialchars($department) ?></div>
                                    <div><strong>Request Date:</strong> <?= $request_date ?></div>
                                </div>

                                <form method="POST" id="requestForm">
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Description</th>
                                                    <th>Unit</th>
                                                    <th>Qty</th>
                                                    <th>Pcs / Box</th>
                                                    <th>Total Pcs</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="itemBody">
                                                <tr>
                                                    <td><input type="text" name="items[0][name]"
                                                            class="form-control form-control-sm" required></td>
                                                    <td><input type="text" name="items[0][description]"
                                                            class="form-control form-control-sm"></td>
                                                    <td>
                                                        <select name="items[0][unit]"
                                                            class="form-select form-select-sm unit unit-select">
                                                            <option value="pcs">Per Piece</option>
                                                            <option value="box">Per Box</option>
                                                        </select>
                                                    </td>
                                                    <td><input type="number" name="items[0][quantity]"
                                                            class="form-control form-control-sm quantity qty-input"
                                                            value="1" min="1"></td>
                                                    <td><input type="number" name="items[0][pcs_per_box]"
                                                            class="form-control form-control-sm pcs-per-box pcs-box-input"
                                                            value="1" min="1" disabled></td>
                                                    <td><input type="number" name="items[0][total_pcs]"
                                                            class="form-control form-control-sm total-pcs total-pcs-input"
                                                            value="1" readonly></td>
                                                    <td><button type="button"
                                                            class="btn btn-sm btn-danger btn-remove">✕</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="text-center mt-3">
                                        <button type="button" id="addRowBtn" class="btn btn-outline-primary">➕ Add
                                            Item</button>
                                    </div>
                                    <div class="text-center mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg">Submit Request</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- My Requests Tab -->
                        <div class="tab-pane fade" id="my-requests" role="tabpanel">
                            <div class="card p-4">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover bg-white">
                                        <thead class="table-dark text-center">
                                            <tr>
                                                <th>ID</th>
                                                <th>Items</th>
                                                <th>Total Requested</th>
                                                <th>Total Approved</th>
                                                <th>Status</th>
                                                <th>Requested At</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($my_requests as $req):
                                $items_array = json_decode($req['items'], true) ?: [];
                                $items_text = implode(", ", array_map(fn($i)=>$i['name'] ?? '', $items_array));
                                $total_requested = $req['total_items'] ?? count($items_array);
                                $total_approved = $req['total_approved_items'] ?? 0;
                                $items_json = htmlspecialchars(json_encode($items_array, JSON_UNESCAPED_UNICODE));
                            ?>
                                            <tr>
                                                <td><?= $req['id'] ?></td>
                                                <td><?= htmlspecialchars($items_text) ?></td>
                                                <td class="text-center"><?= $total_requested ?></td>
                                                <td class="text-center"><?= $total_approved ?></td>
                                                <td>
                                                    <?php
                                        $status = $req['status'];
                                        if($status==='Pending') echo '<span class="badge bg-warning text-dark status-badge">Pending</span>';
                                        elseif($status==='Approved') echo '<span class="badge bg-success status-badge">Approved</span>';
                                        else echo '<span class="badge bg-danger status-badge">Declined</span>';
                                    ?>
                                                </td>
                                                <td><?= $req['created_at'] ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info btn-view-items"
                                                        data-items='<?= $items_json ?>'>View</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal for viewing items -->
                <div class="modal fade" id="viewItemsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Request Items</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th>Item</th>
                                                <th>Description</th>
                                                <th>Unit</th>
                                                <th>Qty Requested</th>
                                                <th>Approved Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modalItemBody"></tbody>
                                    </table>
                                </div>
                            </div>
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

    let itemIndex = 1;
    const addRowBtn = document.getElementById('addRowBtn');
    const itemBody = document.getElementById('itemBody');
    const requestForm = document.getElementById('requestForm');

    addRowBtn.onclick = () => {
        const row = itemBody.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input, select').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
            if (el.name.includes('[name]')) el.value = '';
            if (el.name.includes('[description]')) el.value = '';
            if (el.classList.contains('quantity')) el.value = 1;
            if (el.classList.contains('pcs-per-box')) {
                el.value = 1;
                el.disabled = true;
            }
            if (el.classList.contains('total-pcs')) el.value = 1;
        });
        itemBody.appendChild(row);
        itemIndex++;
    };

    itemBody.addEventListener('click', e => {
        if (e.target.classList.contains('btn-remove')) {
            if (itemBody.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
            else alert("At least one item must be in the request.");
        }
    });

    itemBody.addEventListener('input', e => {
        const row = e.target.closest('tr');
        if (!row) return;
        const unit = row.querySelector('.unit').value;
        const qty = parseFloat(row.querySelector('.quantity').value) || 0;
        const pcsBox = row.querySelector('.pcs-per-box');
        const pcsPerBox = parseFloat(pcsBox.value) || 1;
        row.querySelector('.total-pcs').value = unit === 'box' ? qty * pcsPerBox : qty;
        pcsBox.disabled = unit !== 'box';
    });

    requestForm.addEventListener('submit', e => {
        const rows = Array.from(itemBody.querySelectorAll('tr'));
        const hasItem = rows.some(row => row.querySelector('input[name*="[name]"]').value.trim() !== '');
        if (!hasItem) {
            e.preventDefault();
            alert("Please add at least one item with a name before submitting.");
        }
    });

    // View button logic
    document.querySelectorAll('.btn-view-items').forEach(btn => {
        btn.addEventListener('click', () => {
            const items = JSON.parse(btn.dataset.items);
            const modalBody = document.getElementById('modalItemBody');
            modalBody.innerHTML = '';
            items.forEach(i => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${i.name ?? ''}</td>
                <td>${i.description ?? ''}</td>
                <td>${i.unit ?? ''}</td>
                <td class="text-center">${i.quantity ?? 0}</td>
                <td class="text-center">${i.approved_quantity ?? 0}</td>
            `;
                modalBody.appendChild(tr);
            });
            new bootstrap.Modal(document.getElementById('viewItemsModal')).show();
        });
    });
    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>

</body>

</html>