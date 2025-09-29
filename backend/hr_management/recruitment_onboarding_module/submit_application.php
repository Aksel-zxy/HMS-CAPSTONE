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

    $upload_dir = "applicants document/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

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

        $specific_docs = [
            'resume'        => 'Resume',
            'application_letter'        => 'application_letter',
            'government_id' => 'Government ID',
            'id_picture'    => '2x2 Picture'
        ];

        foreach ($specific_docs as $input_name => $doc_type) {
            if (!empty($_FILES[$input_name]['name']) && $_FILES[$input_name]['error'] === 0) {
                $file_name = time() . "_" . basename($_FILES[$input_name]['name']);
                $file_path = $upload_dir . $file_name;
                move_uploaded_file($_FILES[$input_name]['tmp_name'], $file_path);

                $stmt_doc = $conn->prepare(
                    "INSERT INTO hr_applicant_documents (applicant_id, document_type, file_path, uploaded_at) VALUES (?, ?, ?, NOW())"
                );
                $stmt_doc->bind_param("iss", $applicant_id, $doc_type, $file_path);
                $stmt_doc->execute();
            }
        }

        if (!empty($_FILES['other_documents']['name'][0])) {
            foreach ($_FILES['other_documents']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['other_documents']['error'][$key] === 0) {
                    $file_name = time() . "_" . basename($_FILES['other_documents']['name'][$key]);
                    $file_path = $upload_dir . $file_name;
                    move_uploaded_file($tmp_name, $file_path);

                    $doc_type = $_POST['document_type'][$key] ?? 'Other';

                    $stmt_doc = $conn->prepare(
                        "INSERT INTO hr_applicant_documents (applicant_id, document_type, file_path, uploaded_at) VALUES (?, ?, ?, NOW())"
                    );
                    $stmt_doc->bind_param("iss", $applicant_id, $doc_type, $file_path);
                    $stmt_doc->execute();
                }
            }
        }

        echo "<script>alert('Application submitted successfully!'); window.location.href='../../join_our_team.php';</script>";
    } else {
        echo "<script>alert('Failed to submit application.'); window.history.back();</script>";
    }
}
?>
