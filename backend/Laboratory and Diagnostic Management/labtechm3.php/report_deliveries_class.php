<?php

class labdiagnostic_reportdeliveries {
    private $conn;
    private $table = "labdiagnostic_reportdeliveries";

    //properties
   public $delivery_id;
   public $result_id;
   public $method;
   public $recipient;
   public $delivery_datetime;
   public $status;
   public $attempts;

   public function __construct($db) {
    $this->conn = $db;
   }

   //get all deliveries with test results and patient info
   public function getAll() {
    $query = "SELECT rd.*,
            tr.result_date,
            p.name AS patient_name,
            d.name AS doctor_name,
            t.test_name
        FROM " .$this->table . " rd
          LEFT JOIN labdiagnostic_testresults tr ON rd.result_id = tr.result_id
          LEFT JOIN labdiagnostic_appointments a ON tr.appointment_id = a.appointment_id
          LEFT JOIN patients p ON a.patient_id = p.patient_id
          LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
          LEFT JOIN labdiagnostic_tests t ON a.test_id = t.test_id
          ORDER BY rd.delivery_datetime ASC";

          $stmt = $this->conn->prepare($query);
          $stmt->execute();
          return $stmt;
   }

   //get deliveries by result id
   public function getDeliveriesByResultId($result_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE result_id = ? ORDER BY delivery_datetime ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        return $stmt->get_result();
   }

   //get a single report 
   public function getSingleDelivery() {
        $query = "SELECT * FROM " . $this->table . " WHERE delivery_id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->delivery_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            $this->delivery_id = $row['delivery_id'];
            $this->result_id = $row['result_id'];
            $this->method = $row['method'];
            $this->recipient = $row['recipient'];
            $this->delivery_datetime = $row['delivery_datetime'];
            $this->status = $row['status'];
            $this->attempts = $row['attempts'];
        }
        return $row;
   }

   // create report
   public function create() {
    $query = "INSERT INTO " . $this->table . " (result_id, method, recipient, status, delivery_datetime, attempts)
            VALUES (?, ?, ?, ?, NOW(), 0)";

    $stmt = $this->conn->prepare($query);

    // clean data
    $this->result_id = htmlspecialchars(strip_tags($this->result_id));
    $this->method = htmlspecialchars(strip_tags($this->method));
    $this->recipient = htmlspecialchars(strip_tags($this->recipient));
    $this->status = htmlspecialchars(strip_tags($this->status));

    $stmt->bind_param(
      "isss",
      $this->result_id,
      $this->method,
      $this->recipient,
      $this->status
    );

    if ($stmt->execute()) {
        return true;
    }
    return false;
   }

  //update
  public function update() {
    $query = "UPDATE " . $this->table . "
            SET method = ?, recipient = ?, status = ?, attempts = ?,
                delivery_datetime = " . ($this->status == 'sent' || $this->status == 'delivered' ? 'NOW()' : 'delivery_datetime') . "
            WHERE delivery_id = ?";
    $stmt = $this->conn->prepare($query);

    //clean data
    $this->method = htmlspecialchars(strip_tags($this->method));
    $this->recipient = htmlspecialchars(strip_tags($this->recipient));
    $this->status = htmlspecialchars(strip_tags($this->status));
    $this->attempts = htmlspecialchars(strip_tags($this->attempts));
    $this->delivery_id = htmlspecialchars(strip_tags($this->delivery_id));

    $stmt->bind_param(
      "sssii", 
      $this->method,
      $this->recipient,
      $this->status,
      $this->attempts,
      $this->delivery_id
    );
    if ($stmt->execute()) {
        return true;
    }
    return false;
  }

  //delete
  public function delete() {
    $query = "DELETE FROM " . $this->table . " WHERE delivery_id = ?";
    $stmt = $this->conn->prepare($query);

    $this->delivery_id = htmlspecialchars(strip_tags($this->delivery_id));

    $stmt->bind_param("i", $this->delivery_id);

    if ($stmt->execute()) {
        return true;
    }
    return false;
  }

  // Function to get test result details
    public function getTestResultDetails($result_id) {
        $query = "SELECT tr.*, a.patient_id, p.name as patient_name, p.email  as patient_email,
                      p.phone as patient_phone, p.gender, p.date_of_birth,
                      s.sample_type_id, s.collection_datetime,
                      staff1.name AS performer_name, staff2.name AS verifier_name
               FROM labdiagnostic_testresults tr
               LEFT JOIN labdiagnostic_appointments a ON tr.appointment_id = a.appointment_id
               LEFT JOIN patients p ON a.patient_id = p.patient_id
               LEFT JOIN labdiagnostic_samples s ON tr.sample_id = s.sample_id
               LEFT JOIN staff staff1 ON tr.performed_by = staff1.staff_id
               LEFT JOIN staff staff2 ON tr.verified_by = staff2.staff_id
               WHERE tr.result_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }

    // Function to get result items for a test result
    public function getResultItemsByResultId($result_id) {
        $query = "SELECT * FROM labdiagnostic_resultitems WHERE result_id = ? ORDER BY parameter"; // Adjusted table name
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $resultItems = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $resultItems[] = $row;
            }
        }
        return $resultItems;
    }

    // Function to send email report (simplified for demonstration)
    public function sendEmailReport($testResult, $resultItems, $recipient) {
        // In a real application, you would use a library like PHPMailer
        // This is a simplified example
        $subject = "Lab Test Results - " . $testResult['patient_name'];

        $message = "<html><body>";
        $message .= "<h2>Lab Test Results</h2>";
        $message .= "<p><strong>Patient:</strong> " . $testResult['patient_name'] . "</p>";
        $message .= "<p><strong>Test Date:</strong> " . date('M d, Y', strtotime($testResult['result_date'])) . "</p>";

        if (count($resultItems) > 0) {
            $message .= "<table border='1' cellpadding='5' cellspacing='0'>";
            $message .= "<tr><th>Parameter</th><th>Result</th><th>Normal Range</th><th>Units</th><th>Flag</th></tr>";

            foreach ($resultItems as $item) {
                $message .= "<tr>";
                $message .= "<td>" . $item['parameter'] . "</td>";
                $message .= "<td>" . $item['result_value'] . "</td>";
                $message .= "<td>" . $item['normal_range'] . "</td>";
                $message .= "<td>" . $item['units'] . "</td>";
                $message .= "<td>" . ucfirst($item['flag']) . "</td>";
                $message .= "</tr>";
            }
            $message .= "</table>";
        }

        if (!empty($testResult['conclusion'])) {
            $message .= "<h3>Conclusion</h3>";
            $message .= "<p>" . nl2br($testResult['conclusion']) . "</p>";
        }

        $message .= "<p><em>This is an automated message. Please do not reply.</em></p>";
        $message .= "</body></html>";

        // Headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: lab@example.com" . "\r\n";

        // For this example, we'll just return true to simulate success
        // In a real application, you would use a proper email sending library:
        // return mail($recipient, $subject, $message, $headers);
        return true;
    }
}