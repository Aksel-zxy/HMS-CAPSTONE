<?php
require '../../../SQL/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name     = $_POST['first_name'] ?? '';
    $middle_name    = $_POST['middle_name'] ?? '';
    $last_name      = $_POST['last_name'] ?? '';
    $suffix_name    = $_POST['suffix_name'] ?? '';
    $email          = $_POST['email'] ?? '';
    $phone          = $_POST['phone'] ?? '';
    $address        = $_POST['address'] ?? '';
    $profession     = $_POST['profession'] ?? '';
    $role           = $_POST['job_position'] ?? '';
    $specialization = $_POST['specialization'] ?? '';
    $status         = 'Pending';

    // Insert applicant
    $stmt = $conn->prepare("INSERT INTO hr_applicant
        (first_name, middle_name, last_name, suffix_name, email, phone, address, profession, role, specialization, status, uploaded_at, date_applied)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

    $stmt->bind_param(
        "sssssssssss",
        $first_name,
        $middle_name,
        $last_name,
        $suffix_name,
        $email,
        $phone,
        $address,
        $profession,
        $role,
        $specialization,
        $status
    );

    if ($stmt->execute()) {
        $applicant_id = $stmt->insert_id;

        $all_docs = [
            'resume'             => 'Resume',
            'application_letter' => 'Application Letter',
            'license_id'      => 'License ID',
            'nbi_clearance'      => 'NBI Clearance',
            'id_picture'         => '2x2 Picture',
        ];

        // Handle specific documents
        foreach ($all_docs as $input_name => $doc_type) {
            if (!empty($_FILES[$input_name]['name']) && $_FILES[$input_name]['error'] === 0) {
                $file_data = file_get_contents($_FILES[$input_name]['tmp_name']);
                $stmt_doc = $conn->prepare("
                    INSERT INTO hr_applicant_documents (applicant_id, document_type, file_blob, uploaded_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                // Bind as integer, string, blob
                $stmt_doc->bind_param("iss", $applicant_id, $doc_type, $file_data);
                $stmt_doc->send_long_data(2, $file_data); // index 2 = file_blob
                $stmt_doc->execute();
            }
        }

        // Handle other multiple documents
        if (!empty($_FILES['other_documents']['name'][0])) {
            foreach ($_FILES['other_documents']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['other_documents']['error'][$key] === 0) {
                    $file_data = file_get_contents($tmp_name);
                    $doc_type = $_POST['document_type'][$key] ?? 'Other';

                    $stmt_doc = $conn->prepare("
                        INSERT INTO hr_applicant_documents (applicant_id, document_type, file_blob, uploaded_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt_doc->bind_param("iss", $applicant_id, $doc_type, $file_data);
                    $stmt_doc->send_long_data(2, $file_data);
                    $stmt_doc->execute();
                }
            }
        }

        echo "<script>alert('Application submitted successfully!'); window.location.href='../../join_our_team.php';</script>";
        exit;

    } else {
        echo "<script>alert('Failed to submit application.'); window.history.back();</script>";
        exit;
    }
}
?>
