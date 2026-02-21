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
    <link rel="stylesheet" href="assets/CSS/request.css">


</head>

<body>
    <div class="d-flex">

        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar justify-content-end">

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
                <div class="page-wrap">

                    <!-- ── PAGE HEADER ── -->
                    <div class="page-header">
                        <button class="btn-back" onclick="history.back()" title="Go back">
                            <i class="bi bi-arrow-left-circle-fill"></i>
                            <span>Back</span>
                        </button>
                        <div class="page-header-icon"><i class="bi bi-cart3"></i></div>
                        <h2>Purchase Requests</h2>
                        <span class="date-chip"><i class="bi bi-calendar3 me-1"></i><?= $request_date ?></span>
                    </div>

                    <!-- ── ALERTS ── -->
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success mb-3" id="successAlert">
                        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                    </div>
                    <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger mb-3">
                        <i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <!-- ── TABS ── -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#form" role="tab">
                                <i class="bi bi-plus-circle me-1"></i> Request Form
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#my-requests" role="tab">
                                <i class="bi bi-list-check me-1"></i> My Requests
                                <?php if (!empty($my_requests)): ?>
                                <span
                                    style="background:#00acc1;color:#fff;border-radius:999px;font-size:.65rem;padding:2px 7px;font-weight:800;margin-left:4px;">
                                    <?= count($my_requests) ?>
                                </span>
                                <?php endif; ?>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">

                        <!-- TAB 1 — REQUEST FORM -->
                        <div class="tab-pane fade show active" id="form" role="tabpanel">
                            <div class="pr-card">

                                <div class="info-box">
                                    <div class="info-box-item"><i
                                            class="bi bi-building"></i><strong>Department:</strong>
                                        <?= htmlspecialchars($department) ?></div>
                                    <div class="info-box-item"><i class="bi bi-person"></i><strong>Requestor:</strong>
                                        <?= htmlspecialchars($full_name) ?></div>
                                    <div class="info-box-item"><i class="bi bi-calendar3"></i><strong>Date:</strong>
                                        <?= $request_date ?></div>
                                </div>

                                <form method="POST" id="requestForm">

                                    <div class="items-table-wrap mb-3">
                                        <table class="items-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:22%; text-align:left;">Item Name</th>
                                                    <th style="width:22%; text-align:left;">Description</th>
                                                    <th style="width:13%;">Unit</th>
                                                    <th style="width:10%;">Qty</th>
                                                    <th style="width:11%;">Pcs / Box</th>
                                                    <th style="width:11%;">Total Pcs</th>
                                                    <th style="width:11%;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="itemBody">
                                                <tr>
                                                    <td><input type="text" name="items[0][name]"
                                                            class="form-control form-control-sm" placeholder="Item name"
                                                            required></td>
                                                    <td><input type="text" name="items[0][description]"
                                                            class="form-control form-control-sm" placeholder="Optional">
                                                    </td>
                                                    <td>
                                                        <select name="items[0][unit]"
                                                            class="form-select form-select-sm unit">
                                                            <option value="pcs">Per Piece</option>
                                                            <option value="box">Per Box</option>
                                                        </select>
                                                    </td>
                                                    <td><input type="number" name="items[0][quantity]"
                                                            class="form-control form-control-sm quantity" value="1"
                                                            min="1"></td>
                                                    <td><input type="number" name="items[0][pcs_per_box]"
                                                            class="form-control form-control-sm pcs-per-box" value="1"
                                                            min="1" disabled></td>
                                                    <td><input type="number" name="items[0][total_pcs]"
                                                            class="form-control form-control-sm total-pcs" value="1"
                                                            readonly></td>
                                                    <td><button type="button"
                                                            class="btn-remove-row btn-remove">✕</button></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-center mb-4">
                                        <button type="button" id="addRowBtn" class="btn-add">
                                            <i class="bi bi-plus-circle me-1"></i> Add Item
                                        </button>
                                    </div>

                                    <div class="d-flex justify-content-center">
                                        <button type="submit" class="btn-submit-pr">
                                            <i class="bi bi-send me-2"></i> Submit Request
                                        </button>
                                    </div>

                                </form>
                            </div>
                        </div>

                        <!-- TAB 2 — MY REQUESTS -->
                        <div class="tab-pane fade" id="my-requests" role="tabpanel">
                            <div class="pr-card">

                                <?php if (empty($my_requests)): ?>
                                <div class="empty-state">
                                    <div><i class="bi bi-inbox"></i></div>
                                    <p style="font-weight:600;">No purchase requests yet.</p>
                                    <p style="font-size:.85rem;">Submit your first request using the form tab.</p>
                                </div>
                                <?php else: ?>

                                <!-- Desktop Table -->
                                <div class="req-table-wrap req-table-desktop">
                                    <table class="req-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Department</th>
                                                <th>Total Items</th>
                                                <th>Status</th>
                                                <th>Date Submitted</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($my_requests as $req):
                                    $badge = match($req['status']) {
                                        'Approved' => '<span class="badge-approved">✅ Approved</span>',
                                        'Rejected' => '<span class="badge-rejected">❌ Rejected</span>',
                                        default    => '<span class="badge-pending">⏳ Pending</span>',
                                    };
                                ?>
                                            <tr>
                                                <td><code
                                                        style="font-size:.78rem;">#<?= htmlspecialchars($req['id']) ?></code>
                                                </td>
                                                <td><?= htmlspecialchars($req['department'] ?? $department) ?></td>
                                                <td><?= htmlspecialchars($req['total_items']) ?></td>
                                                <td><?= $badge ?></td>
                                                <td style="font-size:.8rem;color:var(--muted);">
                                                    <?= date('M d, Y', strtotime($req['created_at'])) ?>
                                                    <br><small><?= date('h:i A', strtotime($req['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <button class="btn-view btn-view-items"
                                                        data-id="<?= (int)$req['id'] ?>">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Cards -->
                                <div class="req-mobile-list">
                                    <?php foreach ($my_requests as $req):
                            $badge = match($req['status']) {
                                'Approved' => '<span class="badge-approved">✅ Approved</span>',
                                'Rejected' => '<span class="badge-rejected">❌ Rejected</span>',
                                default    => '<span class="badge-pending">⏳ Pending</span>',
                            };
                        ?>
                                    <div class="req-mobile-card">
                                        <div class="rmc-header">
                                            <span class="rmc-id">#<?= htmlspecialchars($req['id']) ?></span>
                                            <?= $badge ?>
                                        </div>
                                        <div class="rmc-row">
                                            <span class="rmc-label">Total Items</span>
                                            <span class="rmc-val"><?= htmlspecialchars($req['total_items']) ?></span>
                                        </div>
                                        <div class="rmc-row">
                                            <span class="rmc-label">Submitted</span>
                                            <span class="rmc-val" style="font-size:.78rem;">
                                                <?= date('M d, Y · h:i A', strtotime($req['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn-view btn-view-items w-100"
                                                data-id="<?= (int)$req['id'] ?>">
                                                <i class="bi bi-eye me-1"></i> View Items
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- end tab-content -->

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