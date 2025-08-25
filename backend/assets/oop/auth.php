<?php
include __DIR__ . '/../../../SQL/config.php';

class Login
{
    private $conn;
    private $error;

    public function __construct($conn)
    {
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
        // 1️⃣ Check hr_employees table
        $stmt = $this->conn->prepare("SELECT * FROM hr_employees WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();

        if ($employee) {
            $this->processEmployeeLogin($employee, $password);
            return;
        }

        // 2️⃣ Check patient_user table
        $stmt = $this->conn->prepare("SELECT * FROM patient_user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $patient = $result->fetch_assoc();

        if ($patient) {
            $this->processPatientLogin($patient, $password);
            return;
        }

        $this->error = "User not found.";
    }

    private function processUserLogin($user, $password)
    {
        if ($password === $user['password']) { // use password_verify if hashed
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            switch ($user['role']) {
                case '0':
                    $_SESSION['superadmin'] = true;
                    break;
                case '1':
                    $_SESSION['hr'] = true;
                    break;
                case '2':
                    $_SESSION['doctor'] = true;
                    break;
                case '3':
                    $_SESSION['patient'] = true;
                    break;
                case '4':
                    $_SESSION['billing'] = true;
                    break;
                case '5':
                    $_SESSION['pharmacy'] = true;
                    break;
                case '6':
                    $_SESSION['labtech'] = true;
                    break;
                case '7':
                    $_SESSION['inventory'] = true;
                    break;
                case '8':
                    $_SESSION['report'] = true;
                    break;
            }

            $this->redirectBasedOnRole($user['role']);
        } else {
            $this->error = "Incorrect password.";
        }
    }

    private function processEmployeeLogin($employee, $password)
    {
        if ($password === $employee['password']) {
            $_SESSION['employee_id'] = $employee['employee_id'];
            $_SESSION['username'] = $employee['username'];
            $_SESSION['profession'] = $employee['profession'];

            switch ($employee['profession']) {
                case 'Doctor':
                    header("Location: Doctor and Nurse Management/user_panel/user_doctor.php");
                    break;
                case 'Pharmacist':
                    header("Location: Pharmacy Management/user_panel/user_pharmacist.php");
                    break;
                case 'Nurse':
                    header("Location: Doctor and Nurse Management/user_panel/user_nurse.php");
                    break;
                case 'Accountant':
                    header("Location: Billing and Insurance Management/user_panel/user_accountant.php");
                    break;
                case 'Laboratorist':
                    header("Location: Laboratory and Diagnostic Management/user_panel/user_lab.php");
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
        if ($password === $patient['password']) {
            $_SESSION['user_id'] = $patient['user_id'];
            $_SESSION['username'] = $patient['username'];
            $_SESSION['profession'] = 'patient';

            // Redirect patient to their dashboard
            header("Location: Patient Management/user_panel/user_patient.php");
            exit();
        } else {
            $this->error = "Incorrect password.";
        }
    }
    private function redirectBasedOnRole($role)
    {
        switch ($role) {
            case '0':
                header("Location: superadmin_dashboard.php");
                break;
            case '1':
                header("Location: HR Management/admin_dashboard.php");
                break;
            case '2':
                header("Location: Doctor and Nurse Management/doctor_dashboard.php");
                break;
            case '3':
                header("Location: Patient Management/patient_dashboard.php");
                break;
            case '4':
                header("Location: Billing and Insurance Management/billing_dashboard.php");
                break;
            case '5':
                header("Location: Pharmacy Management/pharmacy_dashboard.php");
                break;
            case '6':
                header("Location: Laboratory and Diagnostic Management/labtech_dashboard.php");
                break;
            case '7':
                header("Location: Inventory and Supply Chain Management/inventory_dashboard.php");
                break;
            case '8':
                header("Location: Report and Analytics/report_dashboard.php");
                break;
            default:
                header("Location: login.php?error=Invalid role.");
                break;
        }
        exit;
    }

    public function getError()
    {
        return $this->error;
    }
}

$login = new Login($conn);
$login->authenticate();
