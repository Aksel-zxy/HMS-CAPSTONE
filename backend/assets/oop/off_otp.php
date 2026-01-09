<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include __DIR__ . '/../../../SQL/config.php';
require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/mailer_config.php';

class Login
{
    private $conn;
    private $error;
// change to false to turn off OTP
    private $useOTP = true;  

    public function __construct($conn)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->conn = $conn;
        $this->error = '';
    }

    public function authenticate()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            if ($this->validateInputs($username, $password)) {
                $this->loginUser($username, $password);
            }
        }
    }

    private function validateInputs($username, $password)
    {
        if (empty($username) || empty($password)) {
            $this->error = "Please fill in both fields.";
            return false;
        }
        return true;
    }

    private function loginUser($username, $password)
    {
        // 1. Employee login (no OTP)
        $stmt = $this->conn->prepare("SELECT * FROM hr_employees WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        if ($employee) {
            $this->processEmployeeLogin($employee, $password);
            return;
        }

        // 2. Patient login (no OTP)
        $stmt = $this->conn->prepare("SELECT * FROM patient_user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        if ($patient) {
            $this->processPatientLogin($patient, $password);
            return;
        }

        // 3. Users login (Toggleable OTP)
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $this->processUserLogin($user, $password);
            return;
        }

        $this->error = "User not found.";
    }

    private function processUserLogin($user, $password)
    {
        $dbPassword = $user['password'];

        if (password_verify($password, $dbPassword) || $password === $dbPassword) {
            
            if ($password === $dbPassword) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                $stmt->bind_param("si", $newHash, $user['user_id']);
                $stmt->execute();
            }

            $isRemembered = (!empty($_COOKIE['remember_token']) && $_COOKIE['remember_token'] === $user['remember_token']);

            if (!$this->useOTP || $isRemembered) {
                $this->finalizeUserSession($user);
                $this->redirectByRole($user['role']);
                exit;
            }

            $_SESSION['pending_user_id'] = $user['user_id'];
            $_SESSION['pending_username'] = $user['username'];
            $_SESSION['pending_role'] = $user['role'];
            $_SESSION['remember_me'] = isset($_POST['remember_me']);

            if ($this->sendOTP($user['email'])) {
                header("Location: " . BASE_URL . "backend/assets/auth/verify_otp.php");
                exit;
            }
        } else {
            $this->error = "Incorrect password.";
        }
    }

    private function finalizeUserSession($user)
    {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['otp_verified'] = true;

        $roleMap = ['0'=>'superadmin','1'=>'hr','2'=>'doctor','3'=>'patient','4'=>'billing','5'=>'pharmacy','6'=>'labtech','7'=>'inventory','8'=>'report'];
        if (isset($roleMap[$user['role']])) {
            $_SESSION[$roleMap[$user['role']]] = true;
        }
    }

    private function redirectByRole($role)
    {
        $redirects = [
            '0' => "backend/superadmin_dashboard.php",
            '1' => "backend/hr_management/admin_dashboard.php",
            '2' => "backend/doctor_and_nurse_management/doctor_dashboard.php",
            '3' => "backend/patient_management/patient_dashboard.php",
            '4' => "backend/billing_and_insurance_management/billing_dashboard.php",
            '5' => "backend/pharmacy_management/pharmacy_dashboard.php",
            '6' => "backend/laboratory_and_diagnostic_management/labtech_dashboard.php",
            '7' => "backend/inventory_and_supply_chain_management/inventory_dashboard.php",
            '8' => "backend/report_and_analytics/report_dashboard.php"
        ];
        $location = $redirects[$role] ?? "backend/login.php?error=Invalid role.";
        header("Location: " . BASE_URL . $location);
    }

    private function sendOTP($email)
    {
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = time() + 300; 
        $_SESSION['otp_verified'] = false;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST; $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER; $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = 'tls'; $mail->Port = SMTP_PORT;
            $mail->setFrom('no-reply@hospital.com', 'BSIS-4101 (HMS CAPSTONE)');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code';
            $mail->Body = "<h3>Your OTP is <b>$otp</b></h3>";
            $mail->send();
            return true;
        } catch (Exception $e) {
            $this->error = "OTP failed: {$mail->ErrorInfo}";
            return false;
        }
    }

    private function processEmployeeLogin($employee, $password)
    {
        if (password_verify($password, $employee['password']) || $password === $employee['password']) {
            $_SESSION['employee_id'] = $employee['employee_id'];
            $_SESSION['username'] = $employee['username'];
            $_SESSION['profession'] = $employee['profession'];

            $paths = [
                'Doctor' => "doctor_and_nurse_management/user_panel/Doctor/my_doctor_schedule.php",
                'Pharmacist' => "pharmacy_management/user_panel/user_pharmacist.php",
                'Nurse' => "doctor_and_nurse_management/user_panel/Nurse/my_nurse_schedule.php",
                'Accountant' => "billing_and_insurance_management/user_panel/user_accountant.php",
                'Laboratorist' => "laboratory_and_diagnostic_management/user_panel/user_lab.php"
            ];

            if(isset($paths[$employee['profession']])) {
                header("Location: " . $paths[$employee['profession']]);
                exit;
            }
        }
        $this->error = "Incorrect password.";
    }

    private function processPatientLogin($patient, $password)
    {
        if (password_verify($password, $patient['password']) || $password === $patient['password']) {
            $_SESSION['user_id'] = $patient['user_id'];
            $_SESSION['username'] = $patient['username'];
            $_SESSION['profession'] = 'patient';
            header("Location: patient_management/user_panel/user_patient.php");
            exit;
        }
        $this->error = "Incorrect password.";
    }

    public function getError() { return $this->error; }
}

$login = new Login($conn);
$login->authenticate();