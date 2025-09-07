<?php
session_start();
require 'db.php';

// Fetch all vendors and their contract info
$stmt = $pdo->prepare("
    SELECT id, company_name, company_address, approved_at, contract_end_date
    FROM vendors
    ORDER BY approved_at DESC
");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Vendor Contracts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/Inventory_dashboard.css" rel="stylesheet">
    <link href="assets/css/vendorcontract.css" rel="stylesheet">
    <style>
        .admin-main { margin-left: 250px; padding: 20px; }
        @media (max-width:768px){.admin-main{margin-left:200px;}}
        @media (max-width:480px){.admin-main{margin-left:0;}}
    </style>
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<main class="admin-main">
    <h3 class="mb-4">Vendor Contracts</h3>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Vendor Name</th>
                <th>Contract End Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vendors as $i => $v):
                $approved_at = $v['approved_at'] ? new DateTime($v['approved_at']) : null;
                $contract_end = $v['contract_end_date'] ? new DateTime($v['contract_end_date']) : null;
                $today = new DateTime();

                $status = 'Active';
                $badge_class = 'success';
                if ($contract_end) {
                    $days_left = (int)$today->diff($contract_end)->format('%r%a');
                    if ($days_left < 0) { $status = 'Expired'; $badge_class='danger'; }
                    elseif ($days_left <= 30) { $status = "Expiring in {$days_left} days"; $badge_class='warning'; }
                }
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($v['company_name']) ?></td>
                <td><?= $contract_end ? $contract_end->format('Y-m-d') : '-' ?></td>
                <td><span class="badge bg-<?= $badge_class ?>"><?= $status ?></span></td>
                <td>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#contractModal<?= $v['id'] ?>">
                        View Contract
                    </button>
                </td>
            </tr>

            <!-- Contract Modal -->
            <div class="modal fade" id="contractModal<?= $v['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?= htmlspecialchars($v['company_name']) ?> - Contract Agreement</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <h2 class="text-center mb-4">CONTRACT AGREEMENT</h2>

                            <p>
                                This contract agreement (“Agreement”) is entered into between 
                                <strong>HMS’s Medical Center</strong> (“Hospital”),  
                                and <strong><?= htmlspecialchars($v['company_name']) ?></strong> (“Supplier”),  
                                located at <?= htmlspecialchars($v['company_address']) ?>.
                            </p>

                            <p>
                                WHEREAS, the Hospital requires the supply of medical goods and/or services, and  
                                WHEREAS, the Supplier agrees to provide such goods and/or services under the terms herein.  
                            </p>

                            <h5>1. Contract Duration</h5>
                            <p>
                                This Agreement shall commence upon the Supplier’s approval date of 
                                <strong><?= $approved_at ? $approved_at->format('F j, Y') : 'N/A' ?></strong>  
                                and shall remain in effect until  
                                <strong><?= $contract_end ? $contract_end->format('F j, Y') : 'N/A' ?></strong>,  
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
                                    HMS’s Medical Center
                                </div>
                                <div class="col-md-6 text-center">
                                    ___________________________<br>
                                    Authorized Representative<br>
                                    <?= htmlspecialchars($v['company_name']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
