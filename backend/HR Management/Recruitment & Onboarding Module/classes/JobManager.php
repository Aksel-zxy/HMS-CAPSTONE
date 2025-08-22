<?php
class JobManager {
    private $conn;
    private $uploadDir = "css/pics/";
    private $allowedTypes = ['jpg', 'jpeg', 'png'];
    private $maxFileSize = 2000000; // 2MB

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addJob($data, $file) {
        $imagePath = null;

        if (!empty($file['name'])) {
            $uploadResult = $this->handleImageUpload($file);
            if ($uploadResult['status'] === false) {
                return $uploadResult['message'];
            }
            $imagePath = $uploadResult['filename'];
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO hr_job (title, job_position, job_description, specialization, image) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssss",
            $data['title'],
            $data['job_position'],
            $data['job_description'],
            $data['specialization'],
            $imagePath
        );

        if ($stmt->execute()) {
            return true;
        }
        return "Failed to save job post: " . $this->conn->error;
    }

    public function getJobs() {
        $result = $this->conn->query("SELECT * FROM hr_job ORDER BY date_post DESC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function deleteJob($jobId) {
        $stmt = $this->conn->prepare("DELETE FROM hr_job WHERE job_id = ?");
        $stmt->bind_param("i", $jobId);
        return $stmt->execute();
    }

    private function handleImageUpload($file) {
        $targetFile = $this->uploadDir . basename($file["name"]);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        if (getimagesize($file["tmp_name"]) === false) {
            return ['status' => false, 'message' => "File is not an image."];
        }
        if ($file["size"] > $this->maxFileSize) {
            return ['status' => false, 'message' => "File size exceeds 2MB."];
        }
        if (!in_array($imageFileType, $this->allowedTypes)) {
            return ['status' => false, 'message' => "Only JPG, JPEG, PNG allowed."];
        }
        if (!move_uploaded_file($file["tmp_name"], $targetFile)) {
            return ['status' => false, 'message' => "Error uploading file."];
        }
        return ['status' => true, 'filename' => basename($file["name"])];
    }
}
