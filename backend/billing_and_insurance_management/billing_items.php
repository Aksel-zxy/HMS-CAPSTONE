<?php
session_start();
include '../../SQL/config.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// -----------------------------
// Show patients with completed services if no patient selected
// -----------------------------
if ($patient_id <= 0) {
    $sql = "
        SELECT DISTINCT p.patient_id,
               CONCAT(p.fname, ' ', IFNULL(p.mname, ''), ' ', p.lname) AS full_name
        FROM patientinfo p
        INNER JOIN dl_results dr ON p.patient_id = dr.patientID
        WHERE dr.status='Completed'
          AND p.patient_id NOT IN (
              SELECT DISTINCT patient_id 
              FROM patient_receipt
          )
        ORDER BY p.lname ASC, p.fname ASC
    ";
    $patients = $conn->query($sql);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Select Patient for Billing</title>
        <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    </head>
    <body class="p-4 bg-light">
    <div class="main-sidebar">
        <?php include 'billing_sidebar.php'; ?>
    </div>
    <div class="container bg-white p-4 rounded shadow">
        <h2>Select Patient for Billing</h2>
        <table class="table table-bordered">
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
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------
// Load patient data
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
// Initialize billing cart
// -----------------------------
if (!isset($_SESSION['billing_cart'][$patient_id])) {
    $_SESSION['billing_cart'][$patient_id] = [];

    // Load completed services from dl_results
    $stmt = $conn->prepare("SELECT result FROM dl_results WHERE patientID=? AND status='Completed'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $added_services = [];
    while ($r = $res->fetch_assoc()) {
        $services = explode(",", $r['result']);
        foreach ($services as $srvName) {
            $srvName = trim($srvName);
            if ($srvName == "" || in_array($srvName, $added_services)) continue;

            $stmt2 = $conn->prepare("SELECT * FROM dl_services WHERE serviceName=? LIMIT 1");
            $stmt2->bind_param("s", $srvName);
            $stmt2->execute();
            $srv = $stmt2->get_result()->fetch_assoc();
            if ($srv) {
                $_SESSION['billing_cart'][$patient_id][] = $srv;
                $added_services[] = $srvName; // prevent duplicates
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
// Fetch services not in cart or already billed
// -----------------------------
$billed_services = [];
$stmt = $conn->prepare("SELECT item_id FROM billing_items WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $billed_services[] = $row['item_id']; }

$cart_services = array_column($cart, 'serviceID');
$exclude = array_merge($billed_services, $cart_services);

$sql = "SELECT * FROM dl_services";
$params = [];
$bind_types = "";
if (!empty($exclude)) {
    $placeholders = implode(",", array_fill(0, count($exclude), "?"));
    $sql .= " WHERE serviceID NOT IN ($placeholders)";
    $bind_types = str_repeat("i", count($exclude));
    $params = $exclude;
}
$sql .= " ORDER BY serviceName ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($bind_types, ...$params);
$stmt->execute();
$allServices = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Billing Items</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/CSS/billing_items.css">
<script>
function togglePWD(checkbox){
    let val = checkbox.checked ? 1 : 0;
    window.location.href = "billing_items.php?patient_id=<?= $patient_id ?>&toggle_pwd=" + val;
}
</script>
</head>
<body class="p-4 bg-light">

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container bg-white p-4 rounded shadow">
    <h2>Services for <?= htmlspecialchars($patient['fname'].' '.$patient['lname']) ?></h2>

    <!-- PWD Checkbox -->
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
        <?php while ($srv = $allServices->fetch_assoc()): ?>
            <option value="<?= $srv['serviceID'] ?>">
                <?= htmlspecialchars($srv['serviceName']) ?> - <?= htmlspecialchars($srv['description']) ?> - ₱<?= number_format($srv['price'],2) ?>
            </option>
        <?php endwhile; ?>
    </select>
    <button type="submit" name="add_service" class="btn btn-primary">Add</button>
</form>

    <table class="table table-bordered">
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

    <div class="text-end mt-3">
        Subtotal: ₱<?= number_format($subtotal,2) ?><br>
        Discount: -₱<?= number_format($discount,2) ?><br>
        <strong>Grand Total: ₱<?= number_format($grand_total,2) ?></strong>
    </div>

    <div class="mt-4 d-flex justify-content-between">
        <a href="billing_items.php" class="btn btn-secondary">Back</a>
        <a href="finalize_billing.php?patient_id=<?= $patient_id ?>" class="btn btn-success">Finalize Billing</a>
    </div>
</div>
</body>
</html>
