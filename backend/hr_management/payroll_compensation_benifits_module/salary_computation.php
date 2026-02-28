<?php
require '../../../SQL/config.php';
include '../includes/FooterComponent.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once '../classes/LeaveNotification.php';
require_once 'classes/Salary.php';

Auth::checkHR();

// USER DATA
$userId = Auth::getUserId();
if (!$userId) die("User ID not set.");

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) die("User not found.");

// NOTIFICATIONS
$leaveNotif = new LeaveNotification($conn);
$pendingCount = $leaveNotif->getPendingLeaveCount();

// SALARY OBJECT
$salary = new Salary($conn);
$employees = $salary->getEmployees();

// SESSION MESSAGE
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// SALARY RESULT
$salaryResult = $_SESSION['computed_salary'] ?? null;

// COMPUTE SALARY
if (isset($_POST['compute_salary'])) {
    $employee_id = (int)$_POST['employee_id'];
    $pay_period  = $_POST['pay_period'];
    $period_type = $_POST['period_type'];

    // Compute salary
    $computed = $salary->computeEmployeeSalary($employee_id, $pay_period, $period_type);

    if (!$computed) {
        $_SESSION['message'] = "No compensation data found for this employee.";
        header("Location: salary_computation.php");
        exit;
    }

    // Attach full name and profession for UI
    $emp_row = array_filter($employees, fn($e) => $e['employee_id'] == $employee_id);
    $emp_row = reset($emp_row);
    $computed['full_name']  = $emp_row['full_name'] ?? 'N/A';
    $computed['profession'] = $emp_row['profession'] ?? 'N/A';

    // Check existing payroll
    $existingPayroll = $salary->checkExistingPayroll(
        $employee_id,
        $computed['pay_period_start'],
        $computed['pay_period_end']
    );

    if ($existingPayroll) {
        // Fill missing info for UI
        $existingPayroll['full_name']  = $emp_row['full_name'] ?? 'N/A';
        $existingPayroll['profession'] = $emp_row['profession'] ?? 'N/A';

        // Compute daily rate from basic_pay / total duty days (fallback: compute from attendance)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_duty_days
            FROM hr_daily_attendance
            WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? AND duty_status = 'On Duty'
        ");
        $stmt->bind_param("iss", $employee_id, $existingPayroll['pay_period_start'], $existingPayroll['pay_period_end']);
        $stmt->execute();
        $total_duty_days_full = (int)$stmt->get_result()->fetch_assoc()['total_duty_days'];
        $stmt->close();

        $existingPayroll['daily_rate'] = $total_duty_days_full > 0 
            ? $existingPayroll['basic_pay'] / $total_duty_days_full 
            : 0;

        $_SESSION['computed_salary'] = $existingPayroll;
        $_SESSION['message'] = "Payroll already exists for this period (Status: " . $existingPayroll['status'] . ").";
    } else {
        // Auto save as Pending
        $payrollId = $salary->savePayroll($computed, 'Pending');
        if ($payrollId) {
            $computed['payroll_id'] = $payrollId;
            $computed['status'] = 'Pending';
            $_SESSION['computed_salary'] = $computed;
            $_SESSION['message'] = "Payroll computed and saved as Pending.";
        } else {
            $_SESSION['message'] = "Failed to save payroll.";
        }
    }

    header("Location: salary_computation.php");
    exit;
}

// CLEAR PAYROLL SESSION IF NEEDED
if (isset($_POST['clear_salary'])) {
    unset($_SESSION['computed_salary']);
    header("Location: salary_computation.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | HR Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="css/salary_computation.css">
</head>

<body>

    <!----- Full-page Loader ----->
    <div id="loading-screen">
        <div class="loader">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>
    
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->
        
            <li class="sidebar-item">
                <a href="../admin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill-add" viewBox="0 0 16 16">
                        <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.5-5v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0m-2-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                        <path d="M2 13c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"/>
                    </svg>
                    <span style="font-size: 18px;">Recruitment & Onboarding Management</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../recruitment_onboarding_module/job_management.php" class="sidebar-link">Job Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../recruitment_onboarding_module/applicant_management.php" class="sidebar-link">Applicant Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../recruitment_onboarding_module/onboarding.php" class="sidebar-link">Onboarding</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#geraldd" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard" viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Time & Attendance</span>
                </a>

                <ul id="geraldd" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../time_attendance_module/clock-in_clock-out.php" class="sidebar-link">Clock-In/Clock-Out</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../time_attendance_module/daily_attendance_records.php" class="sidebar-link">Daily Attendance Records</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../time_attendance_module/attendance_reports.php" class="sidebar-link">Attendance Reports</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#geralddd" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/>
                        <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/>
                    </svg>
                    <span style="font-size: 18px;">Leave Management</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>

                <ul id="geralddd" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../leave_management_module/leave_approval.php" class="sidebar-link d-flex justify-content-between align-items-center">
                            Leave Approval
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../leave_management_module/leave_credit_management.php" class="sidebar-link">Leave Credit Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../leave_management_module/leave_reports.php" class="sidebar-link">Leave Reports</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#geraldddd" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cash-stack" viewBox="0 0 16 16">
                        <path d="M1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1zm7 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4"/>
                        <path d="M0 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1zm3 0a2 2 0 0 1-2 2v4a2 2 0 0 1 2 2h10a2 2 0 0 1 2-2V7a2 2 0 0 1-2-2z"/>
                    </svg>
                    <span style="font-size: 18px;">Payroll & Compensation Benifits</span>
                </a>

                <ul id="geraldddd" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="compensation_benifits.php" class="sidebar-link">Compensation & Benifits</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="salary_computation.php" class="sidebar-link">Salary Computation</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="payroll_reports.php" class="sidebar-link">Payroll Reports</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="../department_budget_approval/department_budget_approval.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cash-coin" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M11 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8m5-4a5 5 0 1 1-10 0 5 5 0 0 1 10 0"/>
                        <path d="M9.438 11.944c.047.596.518 1.06 1.363 1.116v.44h.375v-.443c.875-.061 1.386-.529 1.386-1.207 0-.618-.39-.936-1.09-1.1l-.296-.07v-1.2c.376.043.614.248.671.532h.658c-.047-.575-.54-1.024-1.329-1.073V8.5h-.375v.45c-.747.073-1.255.522-1.255 1.158 0 .562.378.92 1.007 1.066l.248.061v1.272c-.384-.058-.639-.27-.696-.563h-.668zm1.36-1.354c-.369-.085-.569-.26-.569-.522 0-.294.216-.514.572-.578v1.1zm.432.746c.449.104.655.272.655.569 0 .339-.257.571-.709.614v-1.195z"/>
                        <path d="M1 0a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h4.083q.088-.517.258-1H3a2 2 0 0 0-2-2V3a2 2 0 0 0 2-2h10a2 2 0 0 0 2 2v3.528c.38.34.717.728 1 1.154V1a1 1 0 0 0-1-1z"/>
                        <path d="M9.998 5.083 10 5a2 2 0 1 0-3.132 1.65 6 6 0 0 1 3.13-1.567"/>
                    </svg>
                    <span style="font-size: 18px;">Department Budget Approval</span>
                </a>
            </li>

        </aside>
        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul"
                            viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="card">
                <h3>Salary Computation (Per Employee)</h3>

                <!-- DISPLAY SESSION MESSAGE -->
                <?php if ($message): ?>
                    <div class="alert alert-info">
                        <?= htmlspecialchars($message); ?>
                        <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items:end;">

                        <!-- Column 1: Employee Info -->
                        <div style="display:grid; gap:0.5rem;">
                            <!-- Employee Select -->
                            <div>
                                <label for="employee_id">Employee</label>
                                <select name="employee_id" id="employee_id" required>
                                    <option value="">----- Select Employee -----</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option 
                                            value="<?= $emp['employee_id']; ?>" 
                                            data-profession="<?= htmlspecialchars($emp['profession'] ?? ''); ?>"
                                            data-role="<?= htmlspecialchars($emp['role'] ?? ''); ?>"
                                            <?= ($salaryResult['employee_id'] ?? '') == $emp['employee_id'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($emp['full_name'] ?? ''); ?> (ID: <?= $emp['employee_id']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Profession Display -->
                            <div>
                                <label>Profession</label>
                                <input type="text" id="profession_display" readonly value="<?= htmlspecialchars($salaryResult['profession'] ?? ''); ?>">
                            </div>

                            <!-- Role Display -->
                            <div>
                                <label>Role</label>
                                <input type="text" id="role_display" readonly value="<?= htmlspecialchars($salaryResult['role'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Column 2: Pay Info + Buttons -->
                        <div style="display:grid; gap:0.5rem;">
                            <!-- Pay Period -->
                            <div>
                                <label>Pay Period</label>
                                <input type="month" name="pay_period" value="<?= $_POST['pay_period'] ?? date('Y-m'); ?>" required>
                            </div>

                            <!-- Period Type -->
                            <div>
                                <label>Period Type</label>
                                <select name="period_type">
                                    <option value="full" <?= (($_POST['period_type'] ?? '') == 'full') ? 'selected' : '' ?>>Full Month</option>
                                    <option value="first" <?= (($_POST['period_type'] ?? '') == 'first') ? 'selected' : '' ?>>1st Half</option>
                                    <option value="second" <?= (($_POST['period_type'] ?? '') == 'second') ? 'selected' : '' ?>>2nd Half</option>
                                </select>
                            </div>

                            <!-- Buttons -->
                            <div style="display:flex; gap:0.5rem;">
                                <button type="submit" name="compute_salary" class="btn btn-primary" style="flex:1;">Compute Salary</button>
                                <button type="submit" name="clear_salary" class="btn btn-secondary" style="flex:1;">Clear</button>
                            </div>
                        </div>

                    </div>
                </form>

                <!-- SALARY RESULT -->
                <?php if ($salaryResult): ?>
                    <div class="salary-box">
                        <div class="salary-header">
                            Salary Breakdown (Status: <?= htmlspecialchars($salaryResult['status'] ?? 'N/A'); ?>)
                        </div>

                        <!-- Earnings -->
                        <div class="salary-body">
                            <table class="salary-table">
                                <tr>
                                    <th>Employee</th>
                                    <td><?= htmlspecialchars($salaryResult['full_name'] ?? 'N/A'); ?></td>

                                </tr>
                                <tr>
                                    <th>Pay Period</th>
                                    <td><?= htmlspecialchars($salaryResult['pay_period_start'] ?? 'N/A'); ?> 
                                        to <?= htmlspecialchars($salaryResult['pay_period_end'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Profession</th>
                                    <td><?= htmlspecialchars($salaryResult['profession'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Daily Rate</th>
                                    <td><?= number_format($salaryResult['daily_rate'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Days Worked</th>
                                    <td><?= number_format($salaryResult['days_worked'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Basic Pay</th>
                                    <td><?= number_format($salaryResult['basic_pay'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Allowances</th>
                                    <td><?= number_format($salaryResult['allowances'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Bonuses</th>
                                    <td><?= number_format($salaryResult['bonuses'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>13th Month</th>
                                    <td><?= number_format($salaryResult['thirteenth_month'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Overtime Hours</th>
                                    <td><?= number_format($salaryResult['overtime_hours'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Overtime Pay</th>
                                    <td><?= number_format($salaryResult['overtime_pay'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Gross Pay (Basic + Allowances + Bonuses + OT + 13th Month)</th>
                                    <td class="gross-pay"><?= number_format($salaryResult['gross_pay'] ?? 0, 2); ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Deductions -->
                        <div class="salary-body">
                            <table class="salary-table">
                                <tr>
                                    <th>Undertime Hours</th>
                                    <td><?= number_format($salaryResult['undertime_hours'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Undertime Deduction</th>
                                    <td><?= number_format($salaryResult['undertime_deduction'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Absence Deduction</th>
                                    <td><?= number_format($salaryResult['absence_deduction'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>SSS Deduction</th>
                                    <td><?= number_format($salaryResult['sss_deduction'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>PhilHealth Deduction</th>
                                    <td><?= number_format($salaryResult['philhealth_deduction'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Pag-IBIG Deduction</th>
                                    <td><?= number_format($salaryResult['pagibig_deduction'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Deductions</th>
                                    <td class="total-deductions"><?= number_format($salaryResult['total_deductions'] ?? 0, 2); ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Net Pay -->
                        <div class="salary-body">
                            <table class="salary-table">
                                <tr class="net-pay">
                                    <th>Gross Pay - Total Deductions = Net Pay</th>
                                    <td><?= number_format($salaryResult['net_pay'] ?? 0, 2); ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Save Payroll Button -->
                        <?php if (($salaryResult['status'] ?? '') !== 'Paid'): ?>
                            <center>
                                <button type="button" id="savePayrollBtn" class="hahaha">Save to Payroll</button>
                            </center>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>
            </div>
            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>

    <!----- Footer Content ----->
    <?php FooterComponent::render();?>

    <!----- End of Footer Content ----->

    <script>
        window.addEventListener("load", () => {
            setTimeout(() => {
                document.getElementById("loading-screen")?.style.setProperty("display", "none");
            }, 2000);
        });

        // Sidebar toggle
        document.querySelector(".toggler-btn")?.addEventListener("click", () => {
            document.querySelector("#sidebar")?.classList.toggle("collapsed");
        });

        // Fetch attendance function
        function fetchAttendance(employeeId, payPeriod) {
            if (!employeeId || !payPeriod) return;

            fetch(`get_attendance.php?employee_id=${employeeId}&pay_period=${payPeriod}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                    } else {
                        // Fill table or form with returned data
                        console.log("Attendance Data:", data);
                    }
                })
                .catch(err => console.error("Attendance fetch error:", err));
        }

        // Save Payroll button
        document.getElementById('savePayrollBtn')?.addEventListener('click', function() {
            const payrollId = <?= json_encode($salaryResult['payroll_id'] ?? null) ?>;

            if (!payrollId) {
                alert("Payroll ID not found. Please compute salary first.");
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerText = 'Saving...';

            fetch('save_payroll.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    payroll_action: 'mark_paid',
                    payroll_id: payrollId
                })
            })
            .then(async res => {
                if (!res.ok) throw new Error("Network response was not OK");
                const text = await res.text();

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("Invalid JSON:", text);
                    throw new Error("Invalid JSON response");
                }

                if (data.success) {
                    alert('Payroll saved! Status updated to Paid.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error("Fetch error:", err);
                alert('An error occurred while processing the request.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerText = 'Save to Payroll';
            });
        });

        // Fetch attendance when employee or period changes
        const employeeSelect = document.getElementById('employee_id');
        const professionInput = document.getElementById('profession_display');
        const roleInput = document.getElementById('role_display');

        employeeSelect.addEventListener('change', () => {
            const selectedOption = employeeSelect.selectedOptions[0];
            professionInput.value = selectedOption.dataset.profession || '';
            roleInput.value = selectedOption.dataset.role || '';
        });

        // Trigger change on page load if an employee is pre-selected
        if(employeeSelect.value) employeeSelect.dispatchEvent(new Event('change'));

        // Debounce helper
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    </script>    
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>