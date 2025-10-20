<?php
class JobManager {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ✅ Add job post
    public function addJob($data, $file) {
        try {
            $profession = trim($data['profession']);
            $title = trim($data['title']);
            $job_position = trim($data['job_position']);
            $job_description = trim($data['job_description']);
            $specialization = trim($data['specialization']);
            $date_post = date("Y-m-d H:i:s");

            // ✅ Handle image as base64
            $imageData = null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $imageTmp = file_get_contents($file['tmp_name']);
                $imageData = base64_encode($imageTmp); // store as base64
            }

            $stmt = $this->conn->prepare("
                INSERT INTO hr_job (profession, title, job_position, job_description, specialization, date_post, image)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssss", $profession, $title, $job_position, $job_description, $specialization, $date_post, $imageData);

            if ($stmt->execute()) {
                return true;
            } else {
                return "Database error: " . $stmt->error;
            }

        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    // ✅ Delete job post
    public function deleteJob($job_id) {
        $stmt = $this->conn->prepare("DELETE FROM hr_job WHERE job_id = ?");
        $stmt->bind_param("i", $job_id);
        return $stmt->execute();
    }

    // ✅ Display job post image (inline)
    public function getJobImage($job_id) {
        $stmt = $this->conn->prepare("SELECT image FROM hr_job WHERE job_id = ?");
        $stmt->bind_param("i", $job_id);
        $stmt->execute();
        $stmt->bind_result($imageData);
        if ($stmt->fetch() && $imageData) {
            echo '<img src="data:image/jpeg;base64,' . htmlspecialchars($imageData) . '" alt="Job Image" style="max-width:100%; height:auto;">';
        }
    }
}

