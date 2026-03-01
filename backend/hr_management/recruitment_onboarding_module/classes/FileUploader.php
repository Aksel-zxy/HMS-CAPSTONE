<?php
class FileUploader {
    private $conn;
    private $allowedFileTypes = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
    private $maxFileSize = 5242880; // 5MB

    public function __construct($db) {
        $this->conn = $db;
    }

    public function uploadDocuments($employee_id, $files) {
        $allowedDocuments = [
            'resume' => 'Resume',
            'license_id' => 'License ID',
            'board_certification' => 'Board Rating & Certificate of Passing',
            'diploma' => 'Diploma',
            'government_id' => 'Government ID',
            'application_letter' => 'Application Letter',
            'tor' => 'Transcript of Records',
            'id_picture' => 'ID Picture',
            'nbi_clearance' => 'NBI Clearance / Police Clearance'
        ];

        foreach ($allowedDocuments as $field => $type) {
            if (!empty($files[$field]['name']) && $files[$field]['error'] === 0) {
                $fileName = basename($files[$field]['name']);
                $fileTmp = $files[$field]['tmp_name'];
                $fileSize = $files[$field]['size'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileSize > $this->maxFileSize) continue;
                if (!in_array($fileExt, $this->allowedFileTypes)) continue;

                // ✅ Read the file as binary data
                $fileData = file_get_contents($fileTmp);

                // ✅ Insert BLOB data into the database
                $stmt = $this->conn->prepare("
                    INSERT INTO hr_employees_documents (employee_id, document_type, file_blob)
                    VALUES (?, ?, ?)
                ");
                $null = NULL; // for sending blob data
                $stmt->bind_param("ssb", $employee_id, $type, $null);
                $stmt->send_long_data(2, $fileData);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
?>
