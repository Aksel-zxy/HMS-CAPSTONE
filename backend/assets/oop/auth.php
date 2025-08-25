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
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows == 1) {
            $user = $result->fetch_assoc();
        
            // Ensure the password is correct (note: use password_verify for hashed passwords)
            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['user_id'];  // Setting user ID
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Debugging session right after setting
                echo '<pre>';
                print_r($_SESSION);  // See if the session is properly populated
                echo '</pre>';
                
                // Set session variables based on the role
                switch ($user['role']) {
                    case '0': // Superadmin
                        $_SESSION['superadmin'] = true;
                        break;
                    case '1': // HR Admin
                        $_SESSION['hr'] = true;
                        break;
                    case '2': // Doctor
                        $_SESSION['doctor'] = true;
                        break;
                    case '3': // Patient Management
                        $_SESSION['patient'] = true;
                        break;
                    case '4': // Billing and Insurance
                        $_SESSION['billing'] = true;
                        break;
                    case '5': // Pharmacy Management
                        $_SESSION['pharmacy'] = true;
                        break;
                    case '6': // Lab Tech
                        $_SESSION['labtech'] = true;
                        break;
                    case '7': // Inventory Management
                        $_SESSION['inventory'] = true;
                        break;
                    case '8': // Report and Analytics
                        $_SESSION['report'] = true;
                        break;
                    default:
                        $this->error = "Invalid role.";
                        return;
                }
        
                // Debugging session again before redirect
                echo '<pre>';
                print_r($_SESSION);  // Check if session variables are properly set
                echo '</pre>';
                
                $this->redirectBasedOnRole($user['role']);
            } else {
                $this->error = "Incorrect password.";
            }
        } else {
            $this->error = "User not found.";
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