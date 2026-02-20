<?php
session_start();
include '../../SQL/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Ensure login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
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

        $stmt = $pdo->prepare("
            INSERT INTO department_request
            (user_id, department, department_id, month, items, total_items, status)
            VALUES
            (:user_id, :department, :department_id, :month, :items, :total_items, 'Pending')
        ");
        $stmt->execute([
            ':user_id'       => $user_id,
            ':department'    => $department,
            ':department_id' => $department_id,
            ':month'         => date('Y-m-d'),
            ':items'         => json_encode($valid_items, JSON_UNESCAPED_UNICODE),
            ':total_items'   => count($valid_items)
        ]);
        $success = "Purchase request successfully submitted!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 🔎 Fetch user's requests
$request_stmt = $pdo->prepare("SELECT * FROM department_request WHERE user_id = ? ORDER BY created_at DESC");
$request_stmt->execute([$user_id]);
$my_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Purchase Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

<body class="bg-light">



    <div class="container py-5">
        <h2 class="mb-4 fw-bold">📋 Purchase Requests</h2>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="requestTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="form-tab" data-bs-toggle="tab" data-bs-target="#form" type="button"
                    role="tab">Request Form</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="my-requests-tab" data-bs-toggle="tab" data-bs-target="#my-requests"
                    type="button" role="tab">My Requests</button>
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
                                                class="form-control form-control-sm quantity qty-input" value="1"
                                                min="1"></td>
                                        <td><input type="number" name="items[0][pcs_per_box]"
                                                class="form-control form-control-sm pcs-per-box pcs-box-input" value="1"
                                                min="1" disabled></td>
                                        <td><input type="number" name="items[0][total_pcs]"
                                                class="form-control form-control-sm total-pcs total-pcs-input" value="1"
                                                readonly></td>
                                        <td><button type="button" class="btn btn-sm btn-danger btn-remove">✕</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-center mt-3">
                            <button type="button" id="addRowBtn" class="btn btn-outline-primary">➕ Add Item</button>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
</body>

</html>