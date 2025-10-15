<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// --- BillEase Config ---
define('BILLEASE_API_KEY', 'YOUR_API_KEY');
define('BILLEASE_ENDPOINT', 'https://sandbox.billease.ph/api/v1/payment');

// --- Get patient ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$selected_patient = null;
if ($patient_id>0){
    $stmt=$conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
    $stmt->bind_param("i",$patient_id);
    $stmt->execute();
    $selected_patient=$stmt->get_result()->fetch_assoc();
}

// --- Fetch services & prices ---
$service_prices = []; 
$billing_items = []; 
$total_charges = 0;

$service_stmt=$conn->query("SELECT serviceName, description, price FROM dl_services");
while($row=$service_stmt->fetch_assoc()){
    $service_prices[$row['serviceName']] = [
        'description' => $row['description'],
        'price' => floatval($row['price'])
    ];
}

if($patient_id>0){
    $stmt=$conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed'");
    $stmt->bind_param("i",$patient_id);
    $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $services = explode(',',$row['result']);
        foreach($services as $s){
            $s = trim($s);
            $price = $service_prices[$s]['price'] ?? 0;
            $desc = $service_prices[$s]['description'] ?? '';
            $billing_items[] = ['service_name'=>$s, 'description'=>$desc, 'total_price'=>$price];
            $total_charges += $price;
        }
    }
}

// --- Insurance ---
$insurance_covered=0; $insurance_company=null;
if($patient_id>0){
    $stmt=$conn->prepare("SELECT insurance_company, SUM(covered_amount) AS total_covered FROM insurance_requests WHERE patient_id=? AND status='Approved' GROUP BY insurance_company ORDER BY total_covered DESC LIMIT 1");
    $stmt->bind_param("i",$patient_id); $stmt->execute();
    $row=$stmt->get_result()->fetch_assoc();
    $insurance_covered=floatval($row['total_covered']??0);
    $insurance_company=$row['insurance_company']??null;
}

// --- Totals ---
$grand_total = $total_charges;
$total_out_of_pocket = max($grand_total - $insurance_covered,0);

// --- Function to create journal entry ---
function createJournalEntry($conn, $patient_id, $grand_total, $insurance_covered, $total_out_of_pocket, $description){
    $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date,module,description,reference_type,status,created_by) VALUES (NOW(),'billing',?,'Patient Billing','Posted',?)");
    $created_by = $_SESSION['username'] ?? 'System';
    $stmt->bind_param("ss",$description,$created_by);
    $stmt->execute();
    $entry_id = $stmt->insert_id;

    if($insurance_covered>0){
        $stmt2 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id,account_name,debit,credit,description) VALUES (?,?,?,?,?)");
        $stmt2->bind_param("isdss",$entry_id,$insurance_account='Accounts Receivable - Insurance',$insurance_covered,$zero=0,$description);
        $stmt2->execute();
    }

    if($total_out_of_pocket>0){
        $stmt2 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id,account_name,debit,credit,description) VALUES (?,?,?,?,?)");
        $stmt2->bind_param("isdss",$entry_id,$account='Cash in Bank - Online',$total_out_of_pocket,$zero=0,$description);
        $stmt2->execute();
    }

    $stmt2 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id,account_name,debit,credit,description) VALUES (?,?,?,?,?)");
    $stmt2->bind_param("isdss",$entry_id,'Service Revenue',$zero=0,$grand_total,$description);
    $stmt2->execute();
}

// --- Handle BillEase payment ---
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pay_online'])){
    $payload = [
        'api_key'=>BILLEASE_API_KEY,
        'amount'=>$total_out_of_pocket,
        'currency'=>'PHP',
        'description'=>"Hospital Bill for Patient #$patient_id",
        'callback_url'=>"https://yourdomain.com/billing_online_callback.php",
        'customer'=>[
            'name'=>$selected_patient['fname'].' '.$selected_patient['lname'],
            'email'=>$selected_patient['email']??'noreply@example.com',
            'phone'=>$selected_patient['phone_number']??'0000000000'
        ]
    ];
    $ch = curl_init(BILLEASE_ENDPOINT);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    $res = json_decode($response,true);
    if(isset($res['payment_url'])) header("Location: ".$res['payment_url']);
    exit;
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/CSS/billing_summary.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="receipt-box">
    <div class="d-flex justify-content-between mb-3">
        <h4>Billing Summary</h4>
        <div><?= date('F j, Y') ?></div>
    </div>

    <div class="mb-2">
        <strong>BILLED TO:</strong><br>
        <?= htmlspecialchars($selected_patient ? ($selected_patient['fname'].' '.$selected_patient['lname']) : 'N/A') ?><br>
        Phone: <?= htmlspecialchars($selected_patient['phone_number'] ?? 'N/A') ?><br>
        Address: <?= htmlspecialchars($selected_patient['address'] ?? 'N/A') ?>
    </div>

    <table class="table table-sm table-bordered mb-3">
        <thead>
            <tr>
                <th>Service</th>
                <th>Description</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($billing_items)): foreach ($billing_items as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($it['service_name']) ?></td>
                    <td><?= htmlspecialchars($it['description']) ?></td>
                    <td class="text-end">₱<?= number_format($it['total_price'],2) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="3" class="text-center">No billed services found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="text-end mb-3">
        <strong>Total Charges: ₱<?= number_format($total_charges,2) ?></strong><br>
        <strong>Insurance Covered: ₱<?= number_format($insurance_covered,2) ?></strong><br>
        <strong>Total: ₱<?= number_format($total_out_of_pocket,2) ?></strong>
    </div>

    <div class="text-end">
        <?php if($total_out_of_pocket>0): ?>
            <form method="POST">
                <button type="submit" name="pay_online" class="btn btn-primary">Pay Online via BillEase</button>
            </form>
        <?php else: ?>
            <div class="text-success">No payment required. Covered by insurance.</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
