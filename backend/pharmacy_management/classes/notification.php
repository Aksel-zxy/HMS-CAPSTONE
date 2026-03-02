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

    // 🔹 Fetch Scheduled Prescriptions Ready for Dispensing
    private function fetchScheduled()
    {
        // Gets all scheduled meds that have remaining doses, assuming we notify for ALL active schedules 
        // that are ongoing/pending OR only those where it is CURRENTLY time to dispense.
        // I will notify for any schedule that is ready to be dispensed right now (time >= allow_time).

        $sql = "
            SELECT sm.schedule_id, 
                   sm.frequency, 
                   sm.duration_days, 
                   sm.start_date,
                   CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
                   CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
                   m.med_name,
                   m.stock_quantity,
                   (SELECT COUNT(*) FROM scheduled_medication_logs WHERE schedule_id = sm.schedule_id) AS doses_given
            FROM scheduled_medications sm
            JOIN patientinfo pi ON sm.patient_id = pi.patient_id
            JOIN hr_employees e ON sm.doctor_id = e.employee_id
            JOIN pharmacy_inventory m ON sm.med_id = m.med_id
            WHERE sm.status IN ('pending', 'ongoing')
        ";

        if ($res = $this->conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $frequency = strtolower(trim($row['frequency']));
                $duration = (int)$row['duration_days'];
                $doses_given = (int)$row['doses_given'];

                $doses_per_day = 1;
                if (preg_match_all('/(\d{1,2}\s*(am|pm))/i', $frequency, $matches)) {
                    $doses_per_day = count($matches[0]);
                } elseif (preg_match('/every\s+(\d+)\s*hours?/', $frequency, $matches)) {
                    $hours = (int)$matches[1];
                    $doses_per_day = ($hours > 0 && $hours <= 24) ? floor(24 / $hours) : 1;
                } elseif (preg_match('/^(\d+)\s*(x|times)\s*(a day|per day)$/i', $frequency, $matches)) {
                    $doses_per_day = (int)$matches[1];
                } else {
                    switch ($frequency) {
                        case 'twice a day':
                            $doses_per_day = 2;
                            break;
                        case 'three times a day':
                            $doses_per_day = 3;
                            break;
                        default:
                            $doses_per_day = 1;
                            break;
                    }
                }

                $total_doses = $doses_per_day * $duration;
                $remaining = $total_doses - $doses_given;

                if ($remaining > 0 && (int)$row['stock_quantity'] > 0) {
                    $interval_hours = ($doses_per_day > 0) ? 24 / $doses_per_day : 24;
                    $start_timestamp = strtotime($row['start_date']);
                    $next_dose_time = strtotime("+" . ($doses_given * $interval_hours) . " hours", $start_timestamp);
                    $allow_time = strtotime("-5 minutes", $next_dose_time);

                    if (time() >= $allow_time) {
                        $this->notifications[] = [
                            'type' => 'prescription',
                            'message' => "Scheduled Dose Ready: {$row['med_name']} for {$row['patient_name']}",
                            'time' => $this->timeAgo(date('Y-m-d H:i:s', $allow_time)),
                            'link' => "pharmacy_prescription.php#scheduled"
                        ];
                        $this->notifCount++;
                    }
                }
            }
        }
    }

    // 🔹 Load all notifications
    public function load()
    {
        $this->fetchPrescriptions();
        $this->fetchExpiry();
        $this->fetchScheduled();

        // Sort by most recent
        usort($this->notifications, function ($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        // Limit to latest 10
        return array_slice($this->notifications, 0, 10);
    }
}
