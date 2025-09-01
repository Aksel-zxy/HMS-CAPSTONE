<?php

session_start();
include '../../SQL/config.php';

// Include the class files
require_once 'classincludes/billing_records_class.php';

if (!isset($_SESSION['billing']) || $_SESSION['billing'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

class Patient
{
    public $conn;
    public $appointmentsTable = "p_appointments";
    public $patientTable = "patientinfo";

    public function __construct($db)
    {
        $this->conn = $db;
    }
    
    public function getAllPatients()
    {
        $query = "
        SELECT p.*, a.*
        FROM {$this->patientTable} p
        INNER JOIN {$this->appointmentsTable} a 
            ON p.patient_id = a.patient_id
        WHERE a.purpose = 'laboratory'
        ";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getPatientById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, a.*
            FROM {$this->patientTable} p
            INNER JOIN {$this->appointmentsTable} a 
                ON p.patient_id = a.patient_id
            WHERE p.patient_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

class BillingRecords {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Fetch all billing records with related dl_results and insurance_request
     */
    public function getBillingSummary($patientID) {
    $query = "
        SELECT 
            p.patient_id,
            CONCAT(p.fname, ' ', COALESCE(p.mname, ''), ' ', p.lname) AS full_name,
            p.phone_number,
            p.address,
            p.discount,
            ba.assigned_date,
            ba.released_date,
            dr.result AS diagnostic_results,
            ir.insurance_type,
            ir.insurance_covered,
            br.status,
            br.billing_date,
            ds.price,
            (ds.price - (COALESCE(p.discount, 0) + COALESCE(ir.insurance_covered, 0))) AS out_of_pocket
        FROM patientinfo p
        LEFT JOIN p_bed_assignments ba ON p.patient_id = ba.patient_id
        LEFT JOIN dl_results dr ON p.patient_id = dr.patientID
        LEFT JOIN insurance_request ir ON p.patient_id = ir.patient_id
        LEFT JOIN billing_records br ON p.patient_id = br.patient_id
        LEFT JOIN dl_services ds ON dr.result = ds.servicename
        WHERE p.patient_id = ?
    ";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

    
    /**
     * Get all patients with laboratory appointments for selection
     */
    public function getLabPatients() {
        $query = "
            SELECT DISTINCT p.patient_id, p.fname, p.lname 
            FROM patientinfo p
            INNER JOIN p_appointments a ON p.patient_id = a.patient_id
            WHERE a.purpose = 'laboratory'
        ";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

$billing = new BillingRecords($conn);
$getpatient = new Patient($conn);

// Get patient ID from URL or form
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// Get all lab patients for the dropdown
$patients = $billing->getLabPatients();

// Get billing summary if a patient is selected
$services = [];
$selected_patient = null;
if ($patient_id > 0) {
    $services = $billing->getBillingSummary($patient_id);
    $selected_patient = $getpatient->getPatientById($patient_id);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Billing and Insurance Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/billingsummary.css">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">

</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->
        
            <li class="sidebar-item">
                <a href="billing_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Billing Management</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="billing_records.php" class="sidebar-link">Billing Records</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="billing_items.php" class="sidebar-link">Billing Items</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="expense_logs.php" class="sidebar-link">Expense Logs</a>
                    </li>
                </ul>
            </li>
        </aside>
        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul"
                            viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="container-fluid billing-summary-container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 40px;">
        <div>
            <h1 style="margin: 0; font-size: 32px;">Billing Summary</h1>
        </div>
        <div style="text-align: right; font-size: 14px;">
    <strong>Date:</strong> 
    <?php 
        if (!empty($services) && isset($services[0]['billing_date'])) {
            echo date('d F Y', strtotime($services[0]['billing_date']));
        } else {
            echo date('d F Y');
        }
    ?>
</div>

    </div>

    <!-- Patient Info -->
    <div style="margin-bottom: 40px; font-size: 14px;">
        <strong>BILLED TO:</strong>
        <br>
        <?php 
            if (!empty($services) && isset($services[0]['full_name'])) {
                echo htmlspecialchars(trim($services[0]['full_name']));
            } elseif (!empty($selected_patient)) {
                $fullName = $selected_patient['fname'] . ' ' . 
                            (!empty($selected_patient['mname']) ? $selected_patient['mname'] . ' ' : '') . 
                            $selected_patient['lname'];
                echo htmlspecialchars($fullName);
            } else {
                echo "N/A";
            }
        ?><br>
        <?php 
            if (!empty($services) && isset($services[0]['phone_number'])) {
                echo htmlspecialchars($services[0]['phone_number']);
            } elseif (!empty($selected_patient['phone_number'])) {
                echo htmlspecialchars($selected_patient['phone_number']);
            } else {
                echo "N/A";
            }
        ?><br>
        <?php 
            if (!empty($services) && isset($services[0]['address'])) {
                echo htmlspecialchars($services[0]['address']);
            } elseif (!empty($selected_patient['address'])) {
                echo htmlspecialchars($selected_patient['address']);
            } else {
                echo "N/A";
            }
        ?>
    </div>

    <!-- Billing Table -->
    <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 40px;">
        <thead>
            <tr>
                <th style="border-bottom: 1px solid #ccc; text-align: left; padding-bottom: 8px;">Particulars</th>
                <th style="border-bottom: 1px solid #ccc; text-align: right; padding-bottom: 8px;">Actual Charges</th>
                <th style="border-bottom: 1px solid #ccc; text-align: right; padding-bottom: 8px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $subtotal = 0;
                $insurance_covered = 0;
                // Find insurance_covered only once (first non-zero value)
                if (!empty($services)) {
                    foreach ($services as $service) {
                        $unit_price = $service['price'] ?? 0;
                        $subtotal += $unit_price;
                        if ($insurance_covered == 0 && !empty($service['insurance_covered'])) {
                            $insurance_covered = $service['insurance_covered'];
                        }
                    }
                    foreach ($services as $service):
            ?>
            <tr>
                <td style="padding: 10px 0;"><?= htmlspecialchars($service['diagnostic_results']) ?></td>
                <td style="padding: 10px 0; text-align: right;">₱<?= number_format($service['price'] ?? 0, 2) ?></td>
                <td style="padding: 10px 0; text-align: right;">₱<?= number_format($service['price'] ?? 0, 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php } else { ?>
            <tr>
                <td colspan="3" style="text-align: center; padding: 20px;">No services found.</td>
            </tr>
            <?php } ?>
        </tbody>
    </table>

    <!-- Totals -->
    <?php if (!empty($services)): ?>
    <table style="width: 100%; font-size: 14px;">
        <tr>
            <td style="text-align: right; padding: 4px 0;">Insurance Covered:</td>
            <td style="text-align: right; padding: 4px 0; color: red;">- ₱<?= number_format($insurance_covered, 2) ?></td>
        </tr>
        <tr>
            <td style="text-align: right; padding: 4px 0;">Subtotal:</td>
            <td style="text-align: right; padding: 4px 0;">₱<?= number_format($subtotal, 2) ?></td>
        </tr>
        <tr>
            <td style="text-align: right; font-weight: bold; padding-top: 12px;">Total:</td>
            <td style="text-align: right; font-weight: bold; padding-top: 12px;">₱<?= number_format($subtotal - $insurance_covered, 2) ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <!-- Thank You -->
    <p style="margin-top: 60px; font-size: 16px;">Thank you!</p>

    <!-- Optional Payment Info -->
    <div style="margin-top: 30px; font-size: 12px;">
        <strong>PAYMENT INFORMATION</strong><br>
        Bank: N/A<br>
        Account Name: N/A<br>
        Account No: N/A<br>
        Pay by: N/A
    </div>

    <!-- Footer Signature -->
    <div style="margin-top: 40px; font-size: 12px; text-align: right;">
        <strong>N/A</strong><br>
        N/A
    </div>
</div>


                    <?php if (!empty($services) && $patient_id > 0): ?>
                        <div class="text-end mt-3">
                            <a 
                                href="pdf.php?billing_id=<?= isset($services[0]['patient_id']) ? htmlspecialchars($services[0]['patient_id']) : $patient_id ?>" 
                                target="_blank" 
                                class="btn btn-primary"
                            >
                                Generate Receipt
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- END CODING HERE -->
</div>
        <!----- End of Main Content ----->
    </div>
    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>