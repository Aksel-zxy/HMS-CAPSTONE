<?php
session_start();
include '../../SQL/config.php';

// Enable exceptions for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get patient ID
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if ($patient_id <= 0) die("Invalid patient ID.");

// -----------------------------
// Load patient info
// -----------------------------
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found.");

// Compute age
$dob = $patient['dob'];
$age = 0;
if (!empty($dob) && $dob != '0000-00-00') {
    $birth = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}

// -----------------------------
// Initialize session cart for patient
// -----------------------------
if (!isset($_SESSION['billing_cart'][$patient_id])) {
    $_SESSION['billing_cart'][$patient_id] = [];

    // Load previously billed services for this patient
    $billed_services = [];
    $res = $conn->query("
        SELECT ds.serviceID
        FROM billing_items bi
        JOIN dl_services ds ON ds.serviceName = bi.item_description
        JOIN patient_receipt pr ON pr.billing_id = bi.billing_id
        WHERE pr.patient_id = $patient_id
    ");
    while ($row = $res->fetch_assoc()) $billed_services[] = $row['serviceID'];

    // Load completed services that are not yet billed
    $stmt = $conn->prepare("SELECT result FROM dl_results WHERE patientID=? AND status='Completed'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $services = explode(",", $r['result']);
        foreach ($services as $srvName) {
            $srvName = trim($srvName);
            if ($srvName == "") continue;

            $stmt2 = $conn->prepare("SELECT * FROM dl_services WHERE serviceName=? LIMIT 1");
            $stmt2->bind_param("s", $srvName);
            $stmt2->execute();
            $srv = $stmt2->get_result()->fetch_assoc();
            if ($srv && !in_array($srv['serviceID'], $billed_services)) {
                // Avoid duplicates in cart
                $exists = false;
                foreach ($_SESSION['billing_cart'][$patient_id] as $c) {
                    if ($c['serviceID'] == $srv['serviceID']) { $exists = true; break; }
                }
                if (!$exists) $_SESSION['billing_cart'][$patient_id][] = $srv;
            }
        }
    }
}

// -----------------------------
// Add service manually
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $service_id = intval($_POST['service_id']);
    if ($service_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM dl_services WHERE serviceID=? LIMIT 1");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $srv = $stmt->get_result()->fetch_assoc();
        if ($srv) {
            $exists = false;
            foreach ($_SESSION['billing_cart'][$patient_id] as $c) {
                if ($c['serviceID'] == $srv['serviceID']) { $exists = true; break; }
            }
            if (!$exists) $_SESSION['billing_cart'][$patient_id][] = $srv;
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
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
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

// -----------------------------
// Toggle PWD
// -----------------------------
if (isset($_GET['toggle_pwd'])) {
    $_SESSION['is_pwd'][$patient_id] = ($_GET['toggle_pwd'] == 1) ? 1 : 0;
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

// -----------------------------
// Compute totals
// -----------------------------
$cart = $_SESSION['billing_cart'][$patient_id];
$subtotal = array_sum(array_column($cart, 'price'));
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
        // Insert into patient_receipt
        $stmt_receipt = $conn->prepare("
            INSERT INTO patient_receipt
            (patient_id, total_charges, total_discount, total_out_of_pocket, grand_total, billing_date, payment_method, status, transaction_id, payment_reference, is_pwd)
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)
        ");
        $payment_method = "Unpaid";
        $status = "Pending";
        $txn = "TXN" . uniqid();
        $pay_ref = "Not Paid Yet";

        $stmt_receipt->bind_param(
            "iddddssssi",
            $patient_id,
            $subtotal,
            $discount,
            $grand_total,
            $grand_total,
            $payment_method,
            $status,
            $txn,
            $pay_ref,
            $is_pwd
        );
        $stmt_receipt->execute();
        $billing_id = $conn->insert_id;

        // Insert into billing_items
        $stmt_item = $conn->prepare("
            INSERT INTO billing_items 
            (billing_id, item_type, item_description, quantity, unit_price, total_price)
            VALUES (?, 'Service', ?, 1, ?, ?)
        ");
        foreach ($cart as $srv) {
            $srv_name = $srv['serviceName'];
            $unit_price = $srv['price'];
            $total_price = $unit_price;
            $stmt_item->bind_param("isdd", $billing_id, $srv_name, $unit_price, $total_price);
            $stmt_item->execute();
        }

        $conn->commit();
        // Clear cart
        unset($_SESSION['billing_cart'][$patient_id]);
        unset($_SESSION['is_pwd'][$patient_id]);

        header("Location: billing_summary.php?patient_id=$patient_id&billing_id=$billing_id");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Finalize billing error: " . $e->getMessage());
        die("Error finalizing billing.");
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Billing Items</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<script>
function togglePWD(checkbox){
    let val = checkbox.checked ? 1 : 0;
    window.location.href = "billing_items.php?patient_id=<?= $patient_id ?>&toggle_pwd=" + val;
}
</script>
</head>
<body class="p-4 bg-light">
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
    <form method="POST" class="mb-3 d-flex gap-2">
        <select name="service_id" class="form-select" required>
            <option value="">-- Select Service --</option>
            <?php
            $cart_services = array_column($cart, 'serviceID');
            $res = $conn->query("SELECT * FROM dl_services ORDER BY serviceName ASC");
            while ($srv = $res->fetch_assoc()):
                if (in_array($srv['serviceID'], $cart_services)) continue;
            ?>
            <option value="<?= $srv['serviceID'] ?>">
                <?= htmlspecialchars($srv['serviceName']) ?> - ₱<?= number_format($srv['price'],2) ?>
            </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" name="add_service" class="btn btn-primary">Add</button>
    </form>

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
                <td><?= htmlspecialchars($srv['serviceName']) ?></td>
                <td class="text-end">₱<?= number_format($srv['price'],2) ?></td>
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
        <a href="billing_items.php" class="btn btn-secondary">Back</a>
        <a href="billing_items.php?patient_id=<?= $patient_id ?>&finalize=1" class="btn btn-success">Finalize Billing</a>
    </div>
</div>
</body>
</html>
