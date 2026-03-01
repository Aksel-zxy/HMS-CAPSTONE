<?php
session_start();
$conn = new mysqli("localhost", "root", "", "hmscapstone");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ============================
   ADMIT PATIENT
============================ */

if (isset($_POST['admit_patient'])) {

    $patient_id = $_POST['patient_id'];
    $room_type_id = $_POST['room_type_id'];
    $hours_stay = $_POST['hours_stay'];

    $bill_number = "BILL-" . time();

    $room = $conn->query("SELECT * FROM billing_room_types WHERE id=$room_type_id")->fetch_assoc();
    $room_total = $room['price_per_hour'] * $hours_stay;

    $stmt = $conn->prepare("INSERT INTO patient_billing
        (patient_id, bill_number, room_type_id, hours_stay, room_total, gross_total, amount_due)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    $gross = $room_total;
    $stmt->bind_param("isidddd", $patient_id, $bill_number, $room_type_id, $hours_stay, $room_total, $gross, $gross);
    $stmt->execute();

    $_SESSION['billing_id'] = $stmt->insert_id;
    $_SESSION['patient_id'] = $patient_id;

    header("Location: inpatient.php");
}

/* ============================
   ADD ITEM
============================ */

if (isset($_POST['add_item'])) {

    $billing_id = $_SESSION['billing_id'];
    $patient_id = $_SESSION['patient_id'];
    $service_id = $_POST['service_id'];
    $quantity = $_POST['quantity'];

    $service = $conn->query("SELECT * FROM billing_services WHERE service_id=$service_id")->fetch_assoc();
    $unit_price = $service['base_price'];
    $total_price = $unit_price * $quantity;

    $stmt = $conn->prepare("INSERT INTO billing_items
        (billing_id, patient_id, service_id, quantity, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("iiiidd", $billing_id, $patient_id, $service_id, $quantity, $unit_price, $total_price);
    $stmt->execute();

    /* AUTO RECALCULATE TOTALS */
    $conn->query("
        UPDATE patient_billing pb
        SET
        services_total = (
            SELECT IFNULL(SUM(total_price),0)
            FROM billing_items bi
            JOIN billing_services bs ON bi.service_id=bs.service_id
            WHERE bi.billing_id=$billing_id AND bs.item_type='Service'
        ),
        medicines_total = (
            SELECT IFNULL(SUM(total_price),0)
            FROM billing_items bi
            JOIN billing_services bs ON bi.service_id=bs.service_id
            WHERE bi.billing_id=$billing_id AND bs.item_type='Medicine'
        ),
        supplies_total = (
            SELECT IFNULL(SUM(total_price),0)
            FROM billing_items bi
            JOIN billing_services bs ON bi.service_id=bs.service_id
            WHERE bi.billing_id=$billing_id AND bs.item_type='Supply'
        )
        WHERE billing_id=$billing_id
    ");

    $conn->query("
        UPDATE patient_billing
        SET gross_total = room_total + services_total + medicines_total + supplies_total,
            amount_due = gross_total
        WHERE billing_id=$billing_id
    ");

    header("Location: inpatient.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inpatient Billing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h2>🏥 Admit Patient</h2>

<form method="POST" class="card p-3 mb-4">
    <div class="row">
        <div class="col-md-4">
            <label>Patient</label>
            <select name="patient_id" class="form-control" required>
                <?php
                $patients = $conn->query("SELECT * FROM patientinfo");
                while ($p = $patients->fetch_assoc()) {
                    echo "<option value='{$p['patient_id']}'>{$p['fname']} {$p['lname']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-4">
            <label>Room Type</label>
            <select name="room_type_id" class="form-control" required>
                <?php
                $rooms = $conn->query("SELECT * FROM billing_room_types WHERE is_active=1");
                while ($r = $rooms->fetch_assoc()) {
                    echo "<option value='{$r['id']}'>{$r['name']} - ₱{$r['price_per_hour']}/hr</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-4">
            <label>Hours Stay</label>
            <input type="number" name="hours_stay" step="0.01" class="form-control" required>
        </div>
    </div>

    <button type="submit" name="admit_patient" class="btn btn-primary mt-3">Admit</button>
</form>

<?php if(isset($_SESSION['billing_id'])): 
$billing_id = $_SESSION['billing_id'];
$billing = $conn->query("SELECT * FROM patient_billing WHERE billing_id=$billing_id")->fetch_assoc();
?>

<hr>
<h3>🧾 BILL #<?php echo $billing['bill_number']; ?></h3>

<!-- ADD ITEM -->
<form method="POST" class="card p-3 mb-4">
    <div class="row">
        <div class="col-md-6">
            <select name="service_id" class="form-control">
                <?php
                $services = $conn->query("SELECT * FROM billing_services WHERE is_active=1");
                while ($s = $services->fetch_assoc()) {
                    echo "<option value='{$s['service_id']}'>
                        {$s['item_type']} - {$s['name']} (₱{$s['base_price']})
                    </option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="quantity" value="1" class="form-control">
        </div>
        <div class="col-md-3">
            <button type="submit" name="add_item" class="btn btn-success w-100">
                Add to Bill
            </button>
        </div>
    </div>
</form>

<!-- SHOW BILL ITEMS -->
<h4>📋 Bill Items</h4>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Type</th>
            <th>Name</th>
            <th>Qty</th>
            <th>Unit</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $items = $conn->query("
            SELECT bi.*, bs.name, bs.item_type
            FROM billing_items bi
            JOIN billing_services bs ON bi.service_id=bs.service_id
            WHERE bi.billing_id=$billing_id
        ");

        while ($i = $items->fetch_assoc()) {
            echo "<tr>
                <td>{$i['item_type']}</td>
                <td>{$i['name']}</td>
                <td>{$i['quantity']}</td>
                <td>₱{$i['unit_price']}</td>
                <td>₱{$i['total_price']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>

<!-- BILL SUMMARY -->
<h4 class="mt-4">💰 Billing Summary</h4>
<div class="card p-3">
    <p>Room Total: ₱<?php echo number_format($billing['room_total'],2); ?></p>
    <p>Services: ₱<?php echo number_format($billing['services_total'],2); ?></p>
    <p>Medicines: ₱<?php echo number_format($billing['medicines_total'],2); ?></p>
    <p>Supplies: ₱<?php echo number_format($billing['supplies_total'],2); ?></p>
    <hr>
    <h5><strong>Grand Total: ₱<?php echo number_format($billing['gross_total'],2); ?></strong></h5>
</div>

<?php endif; ?>

</body>
</html>