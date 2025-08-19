<?php
session_start();
include '../../SQL/config.php';
require_once 'classincludes/insurance_request_class.php';
require_once 'classincludes/billing_summary_class.php';

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

// Instantiate InsuranceRequest with $conn
$insuranceRequest = new InsuranceRequest($conn);

// Track if requests should be shown
$show_requests = false;
$success_message = '';
$error_message = '';

// Handle form submission for new request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    // Validate patient_id exists
    $patient_id = $_POST['patient_id'];
    $check_stmt = $conn->prepare("SELECT patient_id FROM patientinfo WHERE patient_id = ?");
    $check_stmt->bind_param("i", $patient_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        $insuranceRequest->create(
            $_POST['patient_id'],
            $_POST['billing_id'],
            $_POST['insurance_type'],
            $_POST['notes']
        );
        // Redirect to logs page after successful submission
        header("Location: insurance_request_logs.php?success=1");
        exit();
    } else {
        $error_message = "Patient ID does not exist. Please enter a valid Patient ID.";
    }
    $check_stmt->close();
}

// Handle status update
if (isset($_GET['action'], $_GET['id']) && in_array($_GET['action'], ['approve', 'decline'])) {
    $status = $_GET['action'] === 'approve' ? 'approved' : 'declined';
    $insuranceRequest->updateStatus($_GET['id'], $status);
    $show_requests = true;
    // Optionally, you can redirect or just show the table
    header("Location: insurance_request.php?show=1");
    exit;
}

// Show requests if requested via GET (after status update)
if (isset($_GET['show']) && $_GET['show'] == 1) {
    $show_requests = true;
}

// Fetch all requests only if needed
$requests = [];
if ($show_requests) {
    $requests = $insuranceRequest->getAll();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insurance Requests</title>
    <link rel="stylesheet" type="text/css" href="../assets/CSS/billingandinsurance.css">
</head>
<body>
<div class="center-wrapper">
    <div class="container">
        <h2>Insurance Requests Form</h2>
        <div class="form-card">
            <?php if (!empty($success_message)): ?>
                <div class="alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert-error">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <label for="patient_id">Patient ID:</label>
                <input type="number" name="patient_id" id="patient_id" required>

                <label for="billing_id">Billing ID:</label>
                <input type="number" name="billing_id" id="billing_id" required>

                <label for="insurance_type">Insurance Type:</label>
                <input type="text" name="insurance_type" id="insurance_type" required>

                <label for="notes">Notes:</label>
                <textarea name="notes" id="notes" rows="2"></textarea>

                <button type="submit" name="create_request">Submit Request</button>
            </form>
        </div>
        <!-- Remove logs table from here -->
        <!-- Optionally, add a link to logs page -->
        <div style="text-align:center;">
            <a href="insurance_request_logs.php">View Insurance Request Logs</a>
        </div>
    </div>
</div>
</body>
</html>