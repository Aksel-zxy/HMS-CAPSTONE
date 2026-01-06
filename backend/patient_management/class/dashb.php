<?php 
include __DIR__ . '/../../../SQL/config.php';


class Dashboard {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }
    
    public static function getAvailableBedsCount($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM p_beds WHERE status = 'Available'");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return (int)$data['count'];
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

    // Get monthly & weekly outpatient visits
    public static function getMonthlyOutpatients($conn) {
    $data = array_fill(0, 12, 0);
    $year = date('Y');

    $sql = "
        SELECT MONTH(created_at) m, COUNT(*) total
        FROM patientinfo
        WHERE admission_type = 'Outpatient'
        AND YEAR(created_at) = ?
        GROUP BY MONTH(created_at)
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

// Weekly outpatient visits (last 7 days)
    public static function getWeeklyOutpatients($conn) {
        $weeklyOutpatients = [];
        $currentDate = date('Y-m-d');

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days", strtotime($currentDate)));

            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM patientinfo 
                WHERE admission_type = 'Outpatient' AND DATE(created_at) = ?
            ");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res->fetch_assoc();
            $weeklyOutpatients[] = (int)$data['count'];
        }

        return $weeklyOutpatients;
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