<?php
class JobManager {
    private $conn;
    private $uploadDir = "../uploads/job_pics/"; // ✅ changed from css/pics/
    private $allowedTypes = ['jpg', 'jpeg', 'png'];
    private $maxFileSize = 2000000; // 2MB

    public function __construct($db) {
        $this->conn = $db;

        // ✅ Ensure upload folder exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    // ✅ Add new job posting
    public function addJob($data, $file) {
        $imagePath = null;

        if (!empty($file['name'])) {
            $uploadResult = $this->handleImageUpload($file);
            if ($uploadResult['status'] === false) {
                return $uploadResult['message'];
            }
            $imagePath = $uploadResult['filename'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO hr_job (title, job_position, job_description, specialization, image)
            VALUES (?, ?, ?, ?, ?)
        ");
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

    // ✅ Fetch all job posts
    public function getJobs() {
        $result = $this->conn->query("SELECT * FROM hr_job ORDER BY date_post DESC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // ✅ Delete job post
    public function deleteJob($jobId) {
        $stmt = $this->conn->prepare("DELETE FROM hr_job WHERE job_id = ?");
        $stmt->bind_param("i", $jobId);
        return $stmt->execute();
    }

    // ✅ Handle image uploads safely
    private function handleImageUpload($file) {
        $targetFile = $this->uploadDir . basename($file["name"]);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Validate if file is an image
        if (getimagesize($file["tmp_name"]) === false) {
            return ['status' => false, 'message' => "File is not an image."];
        }

        // Check file size
        if ($file["size"] > $this->maxFileSize) {
            return ['status' => false, 'message' => "File size exceeds 2MB."];
        }

        // Check allowed file types
        if (!in_array($imageFileType, $this->allowedTypes)) {
            return ['status' => false, 'message' => "Only JPG, JPEG, PNG files are allowed."];
        }

        // Move uploaded file
        if (!move_uploaded_file($file["tmp_name"], $targetFile)) {
            return ['status' => false, 'message' => "Error uploading file. Check folder permissions."];
        }

        // Return relative path for saving in DB
        return ['status' => true, 'filename' => "uploads/job_pics/" . basename($file["name"])];
    }
}


