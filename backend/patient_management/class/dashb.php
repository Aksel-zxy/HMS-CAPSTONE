<?php 
include __DIR__ . '/../../../SQL/config.php';


class Dashboard {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }
    
    public static function getBedsStatus($conn) {
    // Get total beds
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM p_beds");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $totalBeds = (int)$data['total'];

    // Get available beds
    $stmt = $conn->prepare("SELECT COUNT(*) as available FROM p_beds WHERE status = 'available'");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $availableBeds = (int)$data['available'];

    // Calculate occupied beds
    $occupiedBeds = $totalBeds - $availableBeds;

    return [
        'total' => $totalBeds,
        'available' => $availableBeds,
        'occupied' => $occupiedBeds
    ];
    }
   public static function getAppointmentsCount($conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM p_appointments 
        WHERE appointment_date >= CURDATE()
        AND appointment_date < CURDATE() + INTERVAL 1 DAY
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return (int)$data['count'];
}

    public static function getOutpatientsCount($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patientinfo WHERE admission_type = 'Outpatient'");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return (int)$data['count'];
    }
     public static function getRegisteredPatientsCount($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patientinfo WHERE admission_type = 'Registered'");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return (int)$data['count'];
    }
    public static function getInpatientsCount($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patientinfo WHERE admission_type != 'Outpatient' AND admission_type != 'Registered'");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return (int)$data['count'];
    }
    public static function getTotalPatients($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patientinfo");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return (int)$data['count'];
    }
    
    public static function getMonthlyAvgStay($conn) {
    $year = date('Y');
    $month = date('n'); // current month (1-12)

    $sql = "
        SELECT AVG(DATEDIFF(released_date, assigned_date)) AS avg_stay
        FROM p_bed_assignments
        WHERE YEAR(assigned_date) = ? 
          AND MONTH(assigned_date) = ? 
          AND released_date IS NOT NULL
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row && $row['avg_stay'] ? round((float)$row['avg_stay'], 1) : 0;
    }

    public static function getBedOccupancyRate($conn) {
        $totalBeds = 0;
        $occupiedBeds = 0;

        // Get total beds
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM p_beds");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $totalBeds = (int)$data['count'];

        // Get occupied beds
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM p_bed_assignments WHERE released_date IS NULL");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $occupiedBeds = (int)$data['count'];

        if ($totalBeds === 0) {
            return 0; // Avoid division by zero
        }

        return round(($occupiedBeds / $totalBeds) * 100, 1); // Return percentage with 1 decimal place
    }

    public static function getDailyPatientsCount($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patientinfo WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return (int)$data['count'];
    }

    public static function getGrowthRate($conn) {
        $currentMonth = date('n');
        $currentYear = date('Y');

        // Get current month admissions
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM p_bed_assignments 
            WHERE YEAR(assigned_date) = ? 
              AND MONTH(assigned_date) = ?
        ");
        $stmt->bind_param("ii", $currentYear, $currentMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $currentAdmissions = (int)$data['count'];

        // Get previous month admissions
        $previousMonth = $currentMonth === 1 ? 12 : $currentMonth - 1;
        $previousYear = $currentMonth === 1 ? $currentYear - 1 : $currentYear;

        $stmt->bind_param("ii", $previousYear, $previousMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $previousAdmissions = (int)$data['count'];

        if ($previousAdmissions === 0) {
            return 0; // Avoid division by zero
        }

        return round((($currentAdmissions - $previousAdmissions) / max($previousAdmissions, 1)) * 100, 1); // Return percentage with 1 decimal place
    }
}

class ChartData {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }
       
    public static function getMonthlyTotalPatients($conn) {
        $data = array_fill(0, 12, 0);
        $year = date('Y');

        $sql = "
            SELECT MONTH(created_at) AS m, COUNT(*) AS total
            FROM patientinfo
            WHERE YEAR(created_at) = ?
            GROUP BY MONTH(created_at)
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $year);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $data[(int)$row['m'] - 1] = (int)$row['total'];
        }

        return $data;
    }

    // Weekly total patients (last 7 days)
    public static function getWeeklyTotalPatients($conn) {
        $weeklyTotal = [];
        $currentDate = date('Y-m-d');

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days", strtotime($currentDate)));

            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM patientinfo 
                WHERE DATE(created_at) = ?
            ");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res->fetch_assoc();
            $weeklyTotal[] = (int)$data['count'];
        }

        return $weeklyTotal;
    }

    // Get monthly & weekly inpatient admissions
    public static function getMonthlyAdmissions($conn) {
        $monthlyAdmissions = [];
        $currentYear = date('Y');

        for ($month = 1; $month <= 12; $month++) {
            $startDate = "$currentYear-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
            $endDate = date("Y-m-t", strtotime($startDate));

            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM p_bed_assignments WHERE assigned_date BETWEEN ? AND ?");
            $stmt->bind_param("ss", $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $monthlyAdmissions[] = (int)$data['count'];
        }

        return $monthlyAdmissions;
    }

    public static function getWeeklyAdmissions($conn) {
        $weeklyAdmissions = [];
        $currentDate = date('Y-m-d');

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days", strtotime($currentDate)));

            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM p_bed_assignments WHERE DATE(assigned_date) = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $weeklyAdmissions[] = (int)$data['count'];
        }

        return $weeklyAdmissions;
    }

    public static function getMonthlyOutpatients($conn) {
    $data = array_fill(0, 12, 0);
    $year = date('Y');

    $sql = "
        SELECT MONTH(created_at) AS m, COUNT(*) AS total
        FROM patientinfo
        WHERE admission_type = 'Outpatient'
        AND YEAR(created_at) = ?
        GROUP BY MONTH(created_at)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $year);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $data[$row['m'] - 1] = (int)$row['total'];
    }

    $stmt->close();

    return $data;
}

public static function getWeeklyOutpatients($conn) {
    $data = array_fill(0, 7, 0);

    $sql = "
        SELECT DATE(created_at) AS visit_date, COUNT(*) AS total
        FROM patientinfo
        WHERE admission_type = 'Outpatient'
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY visit_date ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $dates[] = date('Y-m-d', strtotime("-$i days"));
    }

    while ($row = $res->fetch_assoc()) {
        $index = array_search($row['visit_date'], $dates);
        if ($index !== false) {
            $data[$index] = (int)$row['total'];
        }
    }

    $stmt->close();

    return $data;
}

    public static function getMonthlyAppointments($conn) {
        $data = array_fill(0, 12, 0);
        $year = date('Y');

        $sql = "
            SELECT MONTH(appointment_date) m, COUNT(*) total
            FROM p_appointments
            WHERE YEAR(appointment_date) = ?
            GROUP BY MONTH(appointment_date)
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $year);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($r = $res->fetch_assoc()) {
            $data[$r['m'] - 1] = (int)$r['total'];
        }

        return $data;
    }

    // Weekly appointments (last 7 days)
    public static function getWeeklyAppointments($conn) {
        $weeklyAppointments = [];
        $currentDate = date('Y-m-d');

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days", strtotime($currentDate)));

            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM p_appointments 
                WHERE DATE(appointment_date) = ?
            ");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res->fetch_assoc();
            $weeklyAppointments[] = (int)$data['count'];
        }

        return $weeklyAppointments;
    }

}



?>