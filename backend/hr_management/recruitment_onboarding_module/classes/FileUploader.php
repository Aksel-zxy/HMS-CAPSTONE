<?php
class FileUploader {
    private $conn;
    private $uploadDir = "employees document/";
    private $allowedFileTypes = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
    private $maxFileSize = 5242880; // 5MB

    public function __construct($db) {
        $this->conn = $db;
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    public function uploadDocuments($employee_id, $files) {
        $allowedDocuments = [
            'resume' => 'Resume',
            'license_id' => 'License ID',
            'board_certification' => 'Board Rating & Certificate of Passing',
            'diploma' => 'Diploma',
            'nbi_clearance' => 'NBI/Police Clearance',
            'government_id' => 'Government ID',
            'birth_certificate' => 'Birth Certificate',
            'good_moral' => 'Certificate of Good Moral',
            'application_letter' => 'Application Letter',
            'medical_certificate' => 'Medical Certificate',
            'tor' => 'Transcript of Records',
            'id_picture' => 'ID Picture'
        ];

        foreach ($allowedDocuments as $field => $type) {
            if (!empty($files[$field]['name']) && $files[$field]['error'] === 0) {
                $fileName = basename($files[$field]['name']);
                $fileTmp = $files[$field]['tmp_name'];
                $fileSize = $files[$field]['size'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $filePath = $this->uploadDir . time() . "_" . $fileName;

                if ($fileSize > $this->maxFileSize) continue;
                if (!in_array($fileExt, $this->allowedFileTypes)) continue;

                if (move_uploaded_file($fileTmp, $filePath)) {
                    $stmt = $this->conn->prepare("INSERT INTO hr_employees_documents (employee_id, document_type, file_path) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $employee_id, $type, $filePath);
                    $stmt->execute();
                }
            }
        }
    }
}
