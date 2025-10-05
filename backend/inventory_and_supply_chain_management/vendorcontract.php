<?php
session_start();
require 'db.php'; // Database connection

// ✅ Ensure vendor is logged in
if (!isset($_SESSION['vendor_id'])) {
    die("Vendor ID is required. Please <a href='vlogin.php'>login</a> again.");
}

$vendorId = $_SESSION['vendor_id'];

// Fetch vendor details
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die("Vendor not found.");
}

// Hospital name
$hospitalName = "BCP’s Medical Center"; 

// Dates
$approved_at   = $vendor['approved_at'] ? new DateTime($vendor['approved_at']) : null;
$contract_end  = $vendor['contract_end_date'] ? new DateTime($vendor['contract_end_date']) : null;
$today         = new DateTime();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contract Agreement</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/vendorsidebar.css"> 
    <link rel="stylesheet" href="assets/css/vendorcontract.css">
</head>
<body class="bg-light">

    <!-- Sidebar -->
    <div class="vendor-sidebar">
        <?php include 'vendorsidebar.php'; ?>
    </div>

    <!-- Main Contract Content -->
    <main class="vendor-main">
        <div class="contract card shadow-sm p-4">
            <h2 class="text-center mb-4">CONTRACT AGREEMENT</h2>

            <p>
                This contract agreement (“Agreement”) is entered into between 
                <strong><?php echo htmlspecialchars($hospitalName); ?></strong> (“Hospital”),  
                and <strong><?php echo htmlspecialchars($vendor['company_name']); ?></strong> (“Supplier”),  
                located at <?php echo htmlspecialchars($vendor['company_address']); ?>.
            </p>

            <p>
                WHEREAS, the Hospital requires the supply of medical goods and/or services, and  
                WHEREAS, the Supplier agrees to provide such goods and/or services under the terms herein.  
            </p>

            <h5>1. Contract Duration</h5>
            <p>
                This Agreement shall commence upon the Supplier’s approval date of 
                <strong><?php echo $approved_at ? $approved_at->format('F j, Y') : 'N/A'; ?></strong>  
                and shall remain in effect until  
                <strong><?php echo $contract_end ? $contract_end->format('F j, Y') : 'N/A'; ?></strong>,  
                unless extended or terminated earlier in accordance with the terms of this Agreement.  
            </p>

            <h5>2. Extension of Contract</h5>
            <p>
                The Hospital reserves the right to extend this Agreement for an additional six (6) months  
                based on the Supplier’s performance in delivery, quality, and compliance with agreed standards.  
            </p>

            <h5>3. Obligations of Supplier</h5>
            <ul>
                <li>Deliver products and/or services on time and in accordance with specifications.</li>
                <li>Ensure compliance with hospital policies, safety standards, and applicable laws.</li>
                <li>Maintain confidentiality of all hospital-related information.</li>
            </ul>

            <h5>4. Obligations of Hospital</h5>
            <ul>
                <li>Provide timely payment for delivered goods and/or services as per agreed terms.</li>
                <li>Facilitate smooth coordination for delivery and logistics.</li>
                <li>Evaluate Supplier’s performance fairly and transparently.</li>
            </ul>

            <h5>5. Termination</h5>
            <p>
                The Hospital reserves the right to terminate this Agreement in case of breach of contract,  
                non-performance, or any act detrimental to the Hospital’s interests.  
            </p>

            <div class="signature row mt-5">
                <div class="col-md-6 text-center">
                    ___________________________<br>
                    Authorized Representative<br>
                    <?php echo htmlspecialchars($hospitalName); ?>
                </div>
                <div class="col-md-6 text-center">
                    ___________________________<br>
                    Authorized Representative<br>
                    <?php echo htmlspecialchars($vendor['company_name']); ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
