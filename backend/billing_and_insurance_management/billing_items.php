<?php
session_start();
include '../../SQL/config.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// Fetch patient info
if ($patient_id <= 0) {
    $sql = "
        SELECT DISTINCT p.patient_id,
               CONCAT(p.fname, ' ', IFNULL(p.mname, ''), ' ', p.lname) AS full_name
        FROM patientinfo p
        INNER JOIN dl_results dr ON p.patient_id = dr.patientID
        WHERE dr.status='Completed'
          AND p.patient_id NOT IN (SELECT DISTINCT patient_id FROM billing_items WHERE finalized=1)
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

// Load patient
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

// Initialize cart
if (!isset($_SESSION['billing_cart'][$patient_id])) {
    $_SESSION['billing_cart'][$patient_id] = [];

    $sql = "SELECT result FROM dl_results WHERE patientID=? AND status='Completed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $services = explode(",", $r['result']);
        foreach ($services as $srvName) {
            $srvName = trim($srvName);
            if ($srvName == "") continue;
            $stmt2 = $conn->prepare("SELECT serviceID, serviceName, description, price FROM dl_services WHERE serviceName=? LIMIT 1");
            $stmt2->bind_param("s", $srvName);
            $stmt2->execute();
            $srv = $stmt2->get_result()->fetch_assoc();
            if ($srv) {
                $_SESSION['billing_cart'][$patient_id][] = $srv;
            }
        }
    }
}

// Add service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $service_id = intval($_POST['service_id']);
    if ($service_id > 0) {
        $stmt = $conn->prepare("SELECT serviceID, serviceName, description, price FROM dl_services WHERE serviceID=? LIMIT 1");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $srv = $stmt->get_result()->fetch_assoc();
        if ($srv) {
            $_SESSION['billing_cart'][$patient_id][] = $srv;
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

// Delete service
if (isset($_GET['delete'])) {
    $index = intval($_GET['delete']);
    if (isset($_SESSION['billing_cart'][$patient_id][$index])) {
        unset($_SESSION['billing_cart'][$patient_id][$index]);
        $_SESSION['billing_cart'][$patient_id] = array_values($_SESSION['billing_cart'][$patient_id]);
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

// PWD toggle
if (isset($_GET['toggle_pwd'])) {
    $_SESSION['is_pwd'][$patient_id] = ($_GET['toggle_pwd'] == 1) ? 1 : 0;
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

$cart = $_SESSION['billing_cart'][$patient_id];
$subtotal = array_sum(array_column($cart, 'price'));
$is_pwd = $_SESSION['is_pwd'][$patient_id] ?? ($patient['is_pwd'] ?? 0);
$is_senior = $age >= 60 ? 1 : 0;
$discount = ($is_pwd || $is_senior) ? $subtotal * 0.20 : 0;
$grand_total = $subtotal - $discount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Billing Items</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/CSS/billing_items.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function togglePWD(checkbox){
    let val = checkbox.checked ? 1 : 0;
    window.location.href = "billing_items.php?patient_id=<?= $patient_id ?>&toggle_pwd=" + val;
}

function finalizeBilling(){
    fetch('finalize_billing.php?patient_id=<?= $patient_id ?>')
    .then(response => response.text())
    .then(data => {
        Swal.fire({
            icon: 'success',
            title: 'Billing Finalized!',
            html: 'Billing has been finalized successfully.<br>Grand Total: ₱ <?= number_format($grand_total,2) ?>',
            confirmButtonColor: '#198754',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = 'billing_items.php';
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while finalizing billing.'
        });
    });
}
</script>
</head>
<body class="p-4 bg-light">
<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container bg-white p-4 rounded shadow">
    <h2>Services for <?= htmlspecialchars($patient['fname'].' '.$patient['lname']) ?></h2>

    <div class="mb-3">
        <label>
            <input type="checkbox" <?= ($age >= 60) ? 'disabled' : '' ?> <?= $is_pwd ? 'checked' : '' ?> onchange="togglePWD(this)">
            Patient is PWD
        </label>
        <?php if ($age >= 60): ?>
            <small class="text-muted">(Senior discount applied automatically)</small>
        <?php endif; ?>
    </div>

    <!-- Add Service -->
    <form method="POST" class="mb-3 d-flex gap-2">
        <select name="service_id" class="form-select" required>
            <option value="">-- Select Service --</option>
            <?php
            // Exclude services already in the cart
            $cart_ids = array_column($cart, 'serviceID');
            if (count($cart_ids) > 0) {
                $placeholders = implode(',', array_fill(0, count($cart_ids), '?'));
                $sql = "SELECT * FROM dl_services WHERE serviceID NOT IN ($placeholders) ORDER BY serviceName ASC";
                $stmt = $conn->prepare($sql);
                $types = str_repeat('i', count($cart_ids));
                $stmt->bind_param($types, ...$cart_ids);
                $stmt->execute();
                $res = $stmt->get_result();
            } else {
                $res = $conn->query("SELECT * FROM dl_services ORDER BY serviceName ASC");
            }

            while ($srv = $res->fetch_assoc()):
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
        <p>Subtotal: ₱<?= number_format($subtotal,2) ?></p>
        <p>Discount: -₱<?= number_format($discount,2) ?></p>
        <h5><strong>Grand Total: ₱<?= number_format($grand_total,2) ?></strong></h5>
    </div>

    <div class="mt-4 d-flex justify-content-between">
        <a href="billing_items.php" class="btn btn-secondary">Back</a>
        <button type="button" class="btn btn-success" onclick="finalizeBilling()">Finalize Billing</button>
    </div>
</div>
</body>
</html>
