<?php
session_start();
require '../../SQL/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// -----------------------------
// Helper: redirect
// -----------------------------
function redirect($url) {
    header("Location: $url");
    exit;
}

// -----------------------------
// Load patient_id if set
// -----------------------------
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// -----------------------------
// Show patient list if no patient_id
// -----------------------------
if ($patient_id <= 0) {
    $patients = [];
    $res = $conn->query("SELECT * FROM patientinfo p JOIN dl_results r ON p.patient_id = r.patientID GROUP BY p.patient_id ORDER BY p.lname, p.fname");
    while ($row = $res->fetch_assoc()) $patients[] = $row;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Patient List</title>
        <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
        <link rel="stylesheet" href="assets/CSS/billing_sidebar.css">
        <style>
            .content-wrapper { margin-left: 250px; padding: 20px; }
        </style>
    </head>
    <body style="background:#f5f5f5;">
        <!-- Sidebar -->
        <div class="main-sidebar">
            <?php include 'billing_sidebar.php'; ?>
        </div>

        <div class="content-wrapper">
            <div class="container bg-white p-4 rounded shadow">
                <h2>Patients with Completed DL Results</h2>
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Patient Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['fname'].' '.$p['lname']) ?></td>
                            <td>
                                <a href="billing_items.php?patient_id=<?= $p['patient_id'] ?>" class="btn btn-primary btn-sm">View Accumulated Services</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------
// Load patient info
// -----------------------------
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found.");
$dob = $patient['dob'] ?? null;
$age = 0;
if (!empty($dob) && $dob != '0000-00-00') {
    $birth = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}

// -----------------------------
// Initialize session cart
// -----------------------------
if (!isset($_SESSION['billing_cart'][$patient_id])) {
    $_SESSION['billing_cart'][$patient_id] = [];
    $res = $conn->prepare("SELECT result FROM dl_results WHERE patientID=? AND status='Completed'");
    $res->bind_param("i", $patient_id);
    $res->execute();
    $res = $res->get_result();
    while ($row = $res->fetch_assoc()) {
        $services = explode(",", $row['result']);
        foreach ($services as $srvName) {
            $srvName = trim($srvName);
            if ($srvName == "") continue;
            $stmt2 = $conn->prepare("SELECT * FROM dl_services WHERE serviceName=? LIMIT 1");
            $stmt2->bind_param("s", $srvName);
            $stmt2->execute();
            $srv = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($srv) $_SESSION['billing_cart'][$patient_id][] = $srv;
        }
    }
}

// -----------------------------
// Add service
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $service_id = intval($_POST['service_id']);
    $stmt = $conn->prepare("SELECT * FROM dl_services WHERE serviceID=? LIMIT 1");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $srv = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($srv) {
        $exists = false;
        foreach ($_SESSION['billing_cart'][$patient_id] as $c) {
            if ($c['serviceID'] == $srv['serviceID']) { $exists = true; break; }
        }
        if (!$exists) $_SESSION['billing_cart'][$patient_id][] = $srv;
    }
    redirect("billing_items.php?patient_id=$patient_id");
}

// -----------------------------
// Delete service
// -----------------------------
if (isset($_GET['delete'])) {
    $index = intval($_GET['delete']);
    if (isset($_SESSION['billing_cart'][$patient_id][$index])) {
        unset($_SESSION['billing_cart'][$patient_id][$index]);
        $_SESSION['billing_cart'][$patient_id] = array_values($_SESSION['billing_cart'][$patient_id]);
    }
    redirect("billing_items.php?patient_id=$patient_id");
}

// -----------------------------
// Compute totals
// -----------------------------
$cart = $_SESSION['billing_cart'][$patient_id] ?? [];
$subtotal = 0.0;
foreach ($cart as $c) $subtotal += (float)($c['price'] ?? 0);
$is_pwd = $_SESSION['is_pwd'][$patient_id] ?? ($patient['is_pwd'] ?? 0);
$discount = ($is_pwd && $age < 60) ? $subtotal * 0.20 : 0;
$grand_total = $subtotal - $discount;

// -----------------------------
// Finalize billing
// -----------------------------
if (isset($_GET['finalize'])) {
    if (empty($cart)) die("No services to finalize.");
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO patient_receipt (patient_id,total_charges,total_discount,total_out_of_pocket,grand_total,billing_date,payment_method,status,is_pwd) VALUES (?,?,?,?,?,CURDATE(),'Unpaid','Pending',?)");
        $stmt->bind_param("idddi", $patient_id,$subtotal,$discount,$grand_total,$grand_total,$is_pwd);
        $stmt->execute();
        $billing_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO billing_items (billing_id,item_type,item_description,quantity,unit_price,total_price) VALUES (?,?,?,1,?,?)");
        foreach ($cart as $srv) {
            $stmt->bind_param("issdd",$billing_id,$srv['serviceName'],$srv['serviceName'],$srv['price'],$srv['price']);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();

        unset($_SESSION['billing_cart'][$patient_id]);
        unset($_SESSION['is_pwd'][$patient_id]);
        redirect("billing_summary.php?patient_id=$patient_id&billing_id=$billing_id");
    } catch (Exception $e) {
        $conn->rollback();
        die("Error finalizing billing: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Billing Items</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/CSS/billing_sidebar.css">
<script>
function togglePWD(checkbox){
    let val = checkbox.checked ? 1 : 0;
    window.location.href = "billing_items.php?patient_id=<?= $patient_id ?>&toggle_pwd=" + val;
}
</script>
<style>
.content-wrapper { margin-left: 250px; padding: 20px; }
</style>
</head>
<body style="background:#f5f5f5;">

<!-- Sidebar -->
<div class="main-sidebar">
    <?php include 'billing_sidebar.php'; ?>
</div>

<div class="content-wrapper">
<div class="container bg-white p-4 rounded shadow">
    <h2>Services for <?= htmlspecialchars($patient['fname'].' '.$patient['lname']) ?></h2>

    <div class="mb-3">
        <label>
            <input type="checkbox" <?= ($age >= 60) ? 'disabled' : '' ?> <?= $is_pwd ? 'checked' : '' ?> onchange="togglePWD(this)">
            Patient is PWD
        </label>
        <?php if ($age >= 60): ?>
            <small class="text-muted">(Senior patient, discount applied automatically)</small>
        <?php endif; ?>
    </div>

    <!-- Add Service -->
    <form method="POST" class="mb-3 d-flex gap-2" autocomplete="off">
        <select name="service_id" class="form-select" required>
            <option value="">-- Select Service --</option>
            <?php
            $cart_services = array_column($cart, 'serviceID');
            $res = $conn->query("SELECT * FROM dl_services ORDER BY serviceName ASC");
            while ($srv = $res->fetch_assoc()) {
                if (in_array($srv['serviceID'], $cart_services)) continue;
                echo '<option value="'.$srv['serviceID'].'">'.htmlspecialchars($srv['serviceName']).' - ₱'.number_format($srv['price'],2).'</option>';
            }
            ?>
        </select>
        <button type="submit" name="add_service" class="btn btn-primary">Add</button>
    </form>

    <!-- Cart Table -->
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Service</th>
                <th class="text-end">Price</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cart as $i => $srv): ?>
            <tr>
                <td><?= htmlspecialchars($srv['serviceName'] ?? '') ?></td>
                <td class="text-end">₱<?= number_format((float) ($srv['price'] ?? 0), 2) ?></td>
                <td class="text-center">
                    <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $i ?>" class="btn btn-danger btn-sm">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="text-end mt-3">
        Subtotal: ₱<?= number_format($subtotal,2) ?><br>
        Discount: -₱<?= number_format($discount,2) ?><br>
        <strong>Grand Total: ₱<?= number_format($grand_total,2) ?></strong>
    </div>

    <div class="mt-4 d-flex justify-content-between">
        <a href="billing_items.php" class="btn btn-secondary">Back to Patients</a>
        <a href="billing_items.php?patient_id=<?= $patient_id ?>&finalize=1" class="btn btn-success">Finalize Billing</a>
    </div>
</div>
</div>
</body>
</html>
