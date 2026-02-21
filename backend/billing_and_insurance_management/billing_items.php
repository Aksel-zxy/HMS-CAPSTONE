<?php
session_start();
include '../../SQL/config.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

/* =========================================================
   PATIENT SELECTION PAGE
========================================================= */
if ($patient_id <= 0) {

    $sql = "
        SELECT DISTINCT p.patient_id,
               CONCAT(p.fname, ' ', IFNULL(p.mname, ''), ' ', p.lname) AS full_name
        FROM patientinfo p
        LEFT JOIN dl_results dr 
            ON p.patient_id = dr.patientID AND dr.status='Completed'
        LEFT JOIN dnm_records dnr
            ON p.patient_id = dnr.duty_id
        LEFT JOIN pharmacy_prescription pp
            ON p.patient_id = pp.patient_id 
            AND pp.payment_type = 'post_discharged'
            AND pp.status = 'Dispensed'
            AND pp.billing_status = 'pending'
        WHERE (dr.patientID IS NOT NULL OR dnr.record_id IS NOT NULL OR pp.prescription_id IS NOT NULL)
          AND p.patient_id NOT IN (
                SELECT DISTINCT patient_id 
                FROM billing_items 
                WHERE finalized = 1
          )
        ORDER BY p.lname ASC, p.fname ASC
    ";

    $patients = $conn->query($sql);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Select Patient for Billing</title>
        <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
        <link rel="stylesheet" href="assets/css/billing_sidebar.css">
        <style>
            .content-wrapper { margin-left: 250px; transition: margin-left 0.3s ease; padding: 20px; }
            .sidebar.closed ~ .content-wrapper { margin-left: 0; }
            @media (max-width: 768px) { .content-wrapper { margin-left: 0; padding: 15px; } }
        </style>
    </head>
    <body class="p-4 bg-light">

    <div class="main-sidebar">
        <?php include 'billing_sidebar.php'; ?>
    </div>

    <div class="content-wrapper">
        <div class="container bg-white p-4 rounded shadow">
            <h2>Select Patient for Billing</h2>
            <table class="table table-bordered table-striped table-responsive">
                <thead class="table-dark">
                    <tr>
                        <th>Patient Name</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($patients && $patients->num_rows > 0): ?>
                    <?php while ($row = $patients->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['full_name']); ?></td>
                            <td class="text-end">
                                <a href="billing_items.php?patient_id=<?= $row['patient_id']; ?>" class="btn btn-primary btn-sm">Manage Billing</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="2" class="text-center">No patients with unbilled completed services.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

/* =========================================================
   LOAD PATIENT INFO
========================================================= */
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found");

/* =========================================================
   AGE COMPUTATION
========================================================= */
$age = 0;
if (!empty($patient['dob']) && $patient['dob'] != '0000-00-00') {
    $birth = new DateTime($patient['dob']);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}

/* =========================================================
   INITIALIZE BILLING CART
========================================================= */
if (!isset($_SESSION['billing_cart'][$patient_id])) {
    $_SESSION['billing_cart'][$patient_id] = [];

    /* ---- Load Lab Services ---- */
    $stmt = $conn->prepare("SELECT result FROM dl_results WHERE patientID=? AND status='Completed'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $services = array_map('trim', explode(",", $r['result']));
        foreach ($services as $srvName) {
            if ($srvName == '') continue;

            $stmt2 = $conn->prepare("SELECT serviceID, serviceName, description, price FROM dl_services WHERE serviceName=? LIMIT 1");
            $stmt2->bind_param("s", $srvName);
            $stmt2->execute();
            $srv = $stmt2->get_result()->fetch_assoc();

            if ($srv) $_SESSION['billing_cart'][$patient_id][] = $srv;
        }
    }

    /* ---- Load DNM Procedures ---- */
    $stmt = $conn->prepare("SELECT procedure_name, amount FROM dnm_records WHERE duty_id=?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $existingNames = array_column($_SESSION['billing_cart'][$patient_id], 'serviceName');

    while ($row = $res->fetch_assoc()) {
        if (in_array($row['procedure_name'], $existingNames)) continue;

        $_SESSION['billing_cart'][$patient_id][] = [
            'serviceID'   => 'DNM-' . md5($row['procedure_name']),
            'serviceName' => $row['procedure_name'],
            'description' => 'Doctor / Nurse Management Procedure',
            'price'       => $row['amount']
        ];
    }

    /* ---- Load Pharmacy Prescriptions (Post-Discharged only) ---- */
    $stmt = $conn->prepare("
        SELECT ppi.item_id, ppi.med_id, ppi.dosage, ppi.frequency,
               ppi.quantity_dispensed, ppi.unit_price, ppi.total_price
        FROM pharmacy_prescription pp
        JOIN pharmacy_prescription_items ppi ON pp.prescription_id = ppi.prescription_id
        WHERE pp.patient_id = ? 
          AND pp.payment_type = 'post_discharged'
          AND pp.status = 'Dispensed'
          AND pp.billing_status = 'pending'
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $existingNames = array_column($_SESSION['billing_cart'][$patient_id], 'serviceName');

    while ($row = $res->fetch_assoc()) {
        $stmt2 = $conn->prepare("SELECT medicine_name FROM medicines WHERE med_id = ? LIMIT 1");
        $stmt2->bind_param("i", $row['med_id']);
        $stmt2->execute();
        $med = $stmt2->get_result()->fetch_assoc();
        $medName = $med ? $med['medicine_name'] : 'Medicine #' . $row['med_id'];

        $label = $medName . ' (' . $row['dosage'] . ', ' . $row['frequency'] . ')';
        if (in_array($label, $existingNames)) continue;

        $_SESSION['billing_cart'][$patient_id][] = [
            'serviceID'   => 'RX-' . $row['item_id'],
            'serviceName' => $label,
            'description' => 'Pharmacy — Qty: ' . $row['quantity_dispensed'] . ' x ₱' . number_format($row['unit_price'], 2),
            'price'       => $row['total_price']
        ];
        $existingNames[] = $label;
    }
}

/* =========================================================
   ADD / DELETE SERVICES & PWD TOGGLE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $service_id = intval($_POST['service_id']);
    if ($service_id > 0) {
        $stmt = $conn->prepare("SELECT serviceID, serviceName, description, price FROM dl_services WHERE serviceID=? LIMIT 1");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $srv = $stmt->get_result()->fetch_assoc();
        if ($srv) $_SESSION['billing_cart'][$patient_id][] = $srv;
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

if (isset($_GET['delete'])) {
    $index = intval($_GET['delete']);
    if (isset($_SESSION['billing_cart'][$patient_id][$index])) {
        unset($_SESSION['billing_cart'][$patient_id][$index]);
        $_SESSION['billing_cart'][$patient_id] = array_values($_SESSION['billing_cart'][$patient_id]);
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

if (isset($_GET['toggle_pwd'])) {
    $_SESSION['is_pwd'][$patient_id] = $_GET['toggle_pwd'] == 1 ? 1 : 0;
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

/* =========================================================
   BILL COMPUTATION
========================================================= */
$cart = $_SESSION['billing_cart'][$patient_id];
$subtotal = array_sum(array_column($cart, 'price'));
$is_pwd = $_SESSION['is_pwd'][$patient_id] ?? ($patient['is_pwd'] ?? 0);
$is_senior = $age >= 60;
$discount = ($is_pwd || $is_senior) ? $subtotal * 0.20 : 0;
$grand_total = $subtotal - $discount;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Billing Items</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/CSS/billing_items.css">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function togglePWD(checkbox){
    let val = checkbox.checked ? 1 : 0;
    window.location.href = "billing_items.php?patient_id=<?= $patient_id ?>&toggle_pwd=" + val;
}
function finalizeBilling(){
    fetch('finalize_billing.php?patient_id=<?= $patient_id ?>')
    .then(r=>r.text())
    .then(d=>{
        Swal.fire({
            icon: 'success',
            title: 'Billing Finalized!',
            html: 'Billing has been finalized successfully.<br>Grand Total: ₱ <?= number_format($grand_total,2) ?>',
            confirmButtonColor: '#198754',
            confirmButtonText: 'OK'
        }).then(()=>{ window.location.href = 'billing_items.php'; });
    }).catch(e=>{
        Swal.fire({icon:'error',title:'Error',text:'An error occurred while finalizing billing.'});
    });
}
</script>
<style>
.content-wrapper { margin-left: 250px; transition: margin-left 0.3s ease; padding: 20px; }
.sidebar.closed ~ .content-wrapper { margin-left: 0; }
@media (max-width: 768px) { .content-wrapper { margin-left: 0; padding: 15px; } }
.table-responsive { overflow-x: auto; }
</style>
</head>
<body class="p-4 bg-light">
<div class="main-sidebar"><?php include 'billing_sidebar.php'; ?></div>
<div class="content-wrapper">
    <div class="container bg-white p-4 rounded shadow">
        <h2>Services for <?= htmlspecialchars($patient['fname'].' '.$patient['lname']) ?></h2>
        <label class="mb-3">
            <input type="checkbox" <?= $is_senior ? 'disabled' : '' ?> <?= $is_pwd ? 'checked' : '' ?> onchange="togglePWD(this)">
            Patient is PWD
        </label>

        <form method="POST" class="d-flex gap-2 mb-3 flex-wrap">
            <select name="service_id" class="form-select flex-grow-1">
                <option value="">-- Select Service --</option>
                <?php
                $cart_ids = array_filter(array_column($cart, 'serviceID'), 'is_numeric');
                $cart_ids = array_values($cart_ids);
                if ($cart_ids) {
                    $ph = implode(',', array_fill(0, count($cart_ids), '?'));
                    $sql = "SELECT * FROM dl_services WHERE serviceID NOT IN ($ph)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(str_repeat('i', count($cart_ids)), ...$cart_ids);
                    $stmt->execute();
                    $res = $stmt->get_result();
                } else {
                    $res = $conn->query("SELECT * FROM dl_services");
                }
                while ($srv = $res->fetch_assoc()):
                ?>
                <option value="<?= $srv['serviceID'] ?>"><?= htmlspecialchars($srv['serviceName']) ?> - ₱<?= number_format($srv['price'],2) ?></option>
                <?php endwhile; ?>
            </select>
            <button name="add_service" class="btn btn-primary">Add</button>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Service</th>
                        <th>Description</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cart as $i => $srv): ?>
                    <tr>
                        <td><?= htmlspecialchars($srv['serviceName']) ?></td>
                        <td><?= htmlspecialchars($srv['description']) ?></td>
                        <td class="text-end">₱<?= number_format($srv['price'],2) ?></td>
                        <td class="text-center">
                            <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $i ?>" class="btn btn-danger btn-sm">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-end mt-3">
            <p>Subtotal: ₱<?= number_format($subtotal,2) ?></p>
            <p>Discount: -₱<?= number_format($discount,2) ?></p>
            <h5><strong>Grand Total: ₱<?= number_format($grand_total,2) ?></strong></h5>
        </div>

        <div class="mt-3 d-flex justify-content-between flex-wrap gap-2">
            <a href="billing_items.php" class="btn btn-secondary">Back</a>
            <button type="button" class="btn btn-success" onclick="finalizeBilling()">Finalize Billing</button>
        </div>
    </div>
</div>
</body>
</html>