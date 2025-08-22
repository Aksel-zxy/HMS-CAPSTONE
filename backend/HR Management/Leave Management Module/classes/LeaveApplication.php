<?php
class LeaveApplication {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function submit($data, $file = null) {
        $uploadPath = null;

        // Handle medical cert upload
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/medical_certs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $filename = uniqid() . '_' . basename($file['name']);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploadPath = $filepath;
            }
        }

        $stmt = $this->conn->prepare("INSERT INTO hr_leave 
            (employee_id, first_name, last_name, profession, role, department, leave_type, leave_start_date, leave_end_date, leave_status, leave_reason, medical_cert)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $leave_status = 'Pending';

        $stmt->bind_param("ssssssssssss",
            $data['employee_id'],
            $data['first_name'],
            $data['last_name'],
            $data['profession'],
            $data['role'],
            $data['department'],
            $data['leave_type'],
            $data['leave_start_date'],
            $data['leave_end_date'],
            $leave_status,
            $data['leave_reason'],
            $uploadPath
        );

        return $stmt->execute();
    }
}
