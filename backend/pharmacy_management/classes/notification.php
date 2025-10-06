<?php
class Notification
{
    private $conn;
    public $notifications = [];
    public $notifCount = 0;

    public function __construct($conn)
    {
        $this->conn = $conn;
        date_default_timezone_set('Asia/Manila');
    }

    // 🔹 Helper function for "time ago"
    private function timeAgo($datetime)
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return $diff . "s ago";
        } elseif ($diff < 3600) {
            return floor($diff / 60) . "m ago";
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . "h ago";
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . "d ago";
        } else {
            return date("M d, Y", $timestamp);
        }
    }

    // 🔹 Fetch Pending Prescriptions
    private function fetchPrescriptions()
    {
        $sql = "
            SELECT p.prescription_id, 
                   p.prescription_date, 
                   CONCAT(e.first_name, ' ', e.last_name) AS doctor_name
            FROM pharmacy_prescription p
            JOIN hr_employees e ON p.doctor_id = e.employee_id
            WHERE p.status = 'Pending' 
              AND e.profession = 'doctor'
            ORDER BY p.prescription_date DESC
        ";

        if ($res = $this->conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $this->notifications[] = [
                    'type' => 'prescription',
                    'message' => "New Prescription Received from Dr. " . $row['doctor_name'],
                    'time' => $this->timeAgo($row['prescription_date']),
                    'link' => "pharmacy_prescription.php?id=" . $row['prescription_id']
                ];
            }
            $this->notifCount += $res->num_rows;
        }
    }

    // 🔹 Fetch Expiry Notifications
    private function fetchExpiry()
    {
        $sql = "
            SELECT i.med_name, b.batch_no, b.expiry_date,
                   DATEDIFF(b.expiry_date, CURDATE()) AS days_left,
                   CASE 
                        WHEN b.expiry_date < CURDATE() THEN 'Expired'
                        WHEN b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Near Expiry'
                        ELSE 'Available'
                   END AS status
            FROM pharmacy_stock_batches b
            JOIN pharmacy_inventory i ON i.med_id = b.med_id
            WHERE b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY b.expiry_date ASC
        ";

        if ($res = $this->conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                if ($row['status'] === 'Near Expiry') {
                    $this->notifications[] = [
                        'type' => 'expiry',
                        'message' => "{$row['med_name']} (Batch {$row['batch_no']}) is about to expire in {$row['days_left']} day(s)",
                        'time' => $this->timeAgo($row['expiry_date']),
                        'link' => "pharmacy_expiry_tracking.php"
                    ];
                } elseif ($row['status'] === 'Expired') {
                    $this->notifications[] = [
                        'type' => 'expiry',
                        'message' => "{$row['med_name']} (Batch {$row['batch_no']}) has already expired",
                        'time' => $this->timeAgo($row['expiry_date']),
                        'link' => "pharmacy_expiry_tracking.php"
                    ];
                }
            }
            $this->notifCount += $res->num_rows;
        }
    }

    // 🔹 Load all notifications
    public function load()
    {
        $this->fetchPrescriptions();
        $this->fetchExpiry();

        // Sort by most recent
        usort($this->notifications, function ($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        // Limit to latest 10
        return array_slice($this->notifications, 0, 10);
    }
}
