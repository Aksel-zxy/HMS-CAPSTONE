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
        // Employee login (no OTP)
        $stmt = $this->conn->prepare("SELECT * FROM hr_employees WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        if ($employee) {
            $this->processEmployeeLogin($employee, $password);
            return;
        }

        // Patient login (no OTP)
        $stmt = $this->conn->prepare("SELECT * FROM patient_user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        if ($patient) {
            $this->processPatientLogin($patient, $password);
            return;
        }

        // Users login (with OTP)
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

    private function sendOTP($email)
    {
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = time() + 300; // 5 min
        $_SESSION['otp_verified'] = false;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = SMTP_PORT;

            $mail->setFrom('no-reply@hospital.com', 'BSIS-4101 (HMS CAPSTONE)');
            $mail->addAddress($email);
            $mail->addReplyTo('no-reply@hospital.com', 'No Reply');
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code';
            $mail->Body    = "<h3>Your OTP is <b>$otp</b></h3><p>Valid for 5 minutes.</p>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            $this->error = "OTP could not be sent. {$mail->ErrorInfo}";
            return false;
        }
    }

    private function processUserLogin($user, $password)
    {
        $dbPassword = $user['password'];

        if (password_verify($password, $dbPassword) || $password === $dbPassword) {
            // Upgrade plain text passwords to hashed
            if ($password === $dbPassword) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                $stmt->bind_param("si", $newHash, $user['user_id']);
                $stmt->execute();
            }
            if (!empty($_COOKIE['remember_token']) && $_COOKIE['remember_token'] === $user['remember_token']) {
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['otp_verified'] = true;
                switch ($_SESSION['role']) {
                    case '0':
                        $_SESSION['superadmin'] = true;
                        header("Location: " . BASE_URL . "backend/superadmin_dashboard.php");
                        break;
                    case '1':
                        $_SESSION['hr'] = true;
                        header("Location: " . BASE_URL . "backend/HR Management/admin_dashboard.php");
                        break;
                    case '2':
                        $_SESSION['doctor'] = true;
                        header("Location: " . BASE_URL . "backend/Doctor and Nurse Management/doctor_dashboard.php");
                        break;
                    case '3':
                        $_SESSION['patient'] = true;
                        header("Location: " . BASE_URL . "backend/patient_management/patient_dashboard.php");
                        break;
                    case '4':
                        $_SESSION['billing'] = true;
                        header("Location: " . BASE_URL . "backend/Billing and Insurance Management/billing_dashboard.php");
                        break;
                    case '5':
                        $_SESSION['pharmacy'] = true;
                        header("Location: " . BASE_URL . "backend/pharmacy_management/pharmacy_dashboard.php");
                        break;
                    case '6':
                        $_SESSION['labtech'] = true;
                        header("Location: " . BASE_URL . "backend/laboratory_and_diagnostic_management/labtech_dashboard.php");
                        break;
                    case '7':
                        $_SESSION['inventory'] = true;
                        header("Location: " . BASE_URL . "backend/Inventory and Supply Chain Management/inventory_dashboard.php");
                        break;
                    case '8':
                        $_SESSION['report'] = true;
                        header("Location: " . BASE_URL . "backend/Report and Analytics/report_dashboard.php");
                        break;
                    default:
                        header("Location: " . BASE_URL . "backend/login.php?error=Invalid role.");
                        break;
                }
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

    private function processEmployeeLogin($employee, $password)
    {
        $dbPassword = $employee['password'];

        if (password_verify($password, $dbPassword) || $password === $dbPassword) {
            if ($password === $dbPassword) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("UPDATE hr_employees SET password=? WHERE employee_id=?");
                $stmt->bind_param("si", $newHash, $employee['employee_id']);
                $stmt->execute();
            }

            $_SESSION['employee_id'] = $employee['employee_id'];
            $_SESSION['username'] = $employee['username'];
            $_SESSION['profession'] = $employee['profession'];

            switch ($employee['profession']) {
                case 'Doctor':
                    header("Location: Doctor and Nurse Management/user_panel/user_doctor.php");
                    break;
                case 'Pharmacist':
                    header("Location: pharmacy_management/user_panel/user_pharmacist.php");
                    break;
                case 'Nurse':
                    header("Location: Doctor and Nurse Management/user_panel/user_nurse.php");
                    break;
                case 'Accountant':
                    header("Location: Billing and Insurance Management/user_panel/user_accountant.php");
                    break;
                case 'Laboratorist':
                    header("Location: laboratory_and_diagnostic_management/user_panel/user_lab.php");
                    break;
                default:
                    $this->error = "Unknown profession.";
                    return;
            }
            exit;
        } else {
            $this->error = "Incorrect password.";
        }
    }

    private function processPatientLogin($patient, $password)
    {
        $dbPassword = $patient['password'];

        if (password_verify($password, $dbPassword) || $password === $dbPassword) {
            if ($password === $dbPassword) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("UPDATE patient_user SET password=? WHERE user_id=?");
                $stmt->bind_param("si", $newHash, $patient['user_id']);
                $stmt->execute();
            }

            $_SESSION['user_id'] = $patient['user_id'];
            $_SESSION['username'] = $patient['username'];
            $_SESSION['profession'] = 'patient';

            header("Location: Patient Management/user_panel/user_patient.php");
            exit();
        } else {
            $this->error = "Incorrect password.";
        }
    }

    public function getError()
    {
        return $this->error;
    }
}

$login = new Login($conn);
$login->authenticate();
