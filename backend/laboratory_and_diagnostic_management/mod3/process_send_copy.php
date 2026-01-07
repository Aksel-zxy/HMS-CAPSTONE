<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../../vendor/phpmailer/phpmailer/src/Exception.php';
require '../../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../../../vendor/phpmailer/phpmailer/src/SMTP.php';
include '../../../SQL/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultID = $_POST['resultID'];
    $patientID = $_POST['patientID'];

    // 1. Fetch Patient and Result Data
    $query = "SELECT p.fname, p.email, s.serviceName, r.result 
              FROM patientinfo p
              JOIN dl_schedule s ON p.patient_id = s.patientid
              JOIN dl_result_schedules rs ON s.scheduleID = rs.scheduleID
              JOIN dl_results r ON rs.resultID = r.resultID
              WHERE r.resultID = ? LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $resultID);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || empty($data['email'])) {
        echo json_encode(['success' => false, 'message' => 'Patient email not found.']);
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        // 2. SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'photosaved727@gmail.com'; 
        $mail->Password   = 'axzb fcrw trfa dwjq';      
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // 3. Recipients & Content
        $mail->setFrom('no-reply@hms.com', 'HMS Diagnostic and Laboratory Management');
        $mail->addAddress($data['email'], $data['fname']);

        $mail->isHTML(true);
        $mail->Subject = "Medical Test Result: " . $data['serviceName'];
        $mail->Body    = "
            <h3>Hello, {$data['fname']}</h3>
            <p>Your laboratory test for <strong>{$data['serviceName']}</strong> has been completed.</p>
            <p><strong>Result Summary:</strong> {$data['result']}</p>
            <p>Please log in to the Patient Portal to view your full official report.</p>
            <br>
            <p>Best Regards,<br>HMS Laboratory Team</p>";

        $mail->send();

        // 4. Update Database Status
        $update = $conn->prepare("UPDATE dl_results SET status = 'Delivered' WHERE resultID = ?");
        $update->bind_param("i", $resultID);
        $update->execute();

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Mail Error: {$mail->ErrorInfo}"]);
    }
}