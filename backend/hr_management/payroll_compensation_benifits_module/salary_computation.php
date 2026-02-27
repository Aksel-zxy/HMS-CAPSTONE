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

$salaryResult = null;
if (isset($_SESSION['computed_salary'])) {
    $salaryResult = $_SESSION['computed_salary'];
}

// COMPUTE SALARY
if (isset($_POST['compute_salary'])) {

    $employee_id = (int)$_POST['employee_id'];
    $pay_period  = $_POST['pay_period'];
    $period_type = $_POST['period_type'];

    $salaryResult = $salary->computeEmployeeSalary(
        $employee_id,
        $pay_period,
        $period_type
    );

    if ($salaryResult) {

        // Attach employee name (UI ONLY)
        foreach ($employees as $emp) {
            if ($emp['employee_id'] == $employee_id) {
                $salaryResult['full_name'] = $emp['full_name'];
                break;
            }
        }

        // 🚨 CHECK IF ALREADY EXISTS (prevent duplicate)
        $existingPayroll = $salary->checkExistingPayroll(
            $employee_id,
            $salaryResult['pay_period_start'],
            $salaryResult['pay_period_end']
        );

        if ($existingPayroll) {

            $_SESSION['computed_salary'] = $existingPayroll;
            $_SESSION['message'] = "Payroll already exists for this period.";

        } else {

            // ✅ AUTO SAVE AS PENDING
            $payrollId = $salary->savePayroll($salaryResult, 'Pending');

            if ($payrollId) {

                $salaryResult['payroll_id'] = $payrollId;
                $salaryResult['status'] = 'Pending';
                $_SESSION['computed_salary'] = $salaryResult;
                $_SESSION['message'] = "Payroll computed and saved as Pending.";

            } else {
                $_SESSION['message'] = "Failed to save payroll.";
            }
        }

        header("Location: salary_computation.php");
        exit;

    } else {
        echo "<script>alert('No compensation data found for this employee.');</script>";
    }
}

//  SAVE PAYROLL
if (isset($_POST['save_payroll'])) {

    if (!isset($_SESSION['computed_salary']['payroll_id'])) {
        $_SESSION['message'] = "No payroll record found.";
        header("Location: salary_computation.php");
        exit;
    }

    $payroll_id = $_SESSION['computed_salary']['payroll_id'];

    if ($salary->markAsPaid($payroll_id)) {

        $_SESSION['computed_salary']['status'] = 'Paid';
        $_SESSION['message'] = "Payroll marked as Paid successfully!";

    } else {
        $_SESSION['message'] = "Failed to update payroll status.";
    }

    header("Location: salary_computation.php");
    exit;
}

if (!empty($_SESSION['message'])) {
    echo "<script>alert('" . $_SESSION['message'] . "');</script>";
    unset($_SESSION['message']);
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
                <h3> Salary Computation (Per Employee) </h3>

                <form method="POST">
                    <div class="form-grid">
                        <div>
                            <label>Employee</label>
                            <select name="employee_id" id="employee_id" required>
                                <option value="">----- Select Employee -----</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>">
                                        <?php echo htmlspecialchars($emp['full_name'] ?? ''); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (Employee ID: <?php echo $emp['employee_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pay Period</label>
                            <input type="month" name="pay_period" id="pay_period" value="<?= $_POST['pay_period'] ?? date('Y-m'); ?>" required>
                        </div>

                        <div>
                            <label>Period Type</label>
                            <select name="period_type">
                                <option value="full" <?= (isset($_POST['period_type']) && $_POST['period_type']=='full') ? 'selected' : '' ?>>Full Month</option>
                                <option value="first" <?= (isset($_POST['period_type']) && $_POST['period_type']=='first') ? 'selected' : '' ?>>1st Half</option>
                                <option value="second" <?= (isset($_POST['period_type']) && $_POST['period_type']=='second') ? 'selected' : '' ?>>2nd Half</option>
                            </select>
                        </div>

                        <div>
                            <button type="submit" name="compute_salary" class="haha">Compute Salary</button>
                        </div>
                    </div>
                </form>

                <!-- RESULT -->
                <?php if ($salaryResult): ?>
                    <div class="salary-box">

                        <div class="salary-header">
                            Salary Breakdown
                        </div>

                        <div class="salary-body">
                            <table class="salary-table">
                                <tr>
                                    <th>Employee</th>
                                    <td><?= htmlspecialchars($salaryResult['full_name'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Pay Period</th>
                                    <td>
                                        <?= htmlspecialchars($salaryResult['pay_period_start'] ?? 'N/A'); ?>
                                        to
                                        <?= htmlspecialchars($salaryResult['pay_period_end'] ?? 'N/A'); ?>
                                    </td>
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
                        <div class="salary-body">
                            <table class="salary-table">
                                <tr class="net-pay">
                                    <th>Gross Pay - Total Deductions = Net Pay</th>
                                    <td><?= number_format($salaryResult['net_pay'] ?? 0, 2); ?></td>
                                </tr>
                            </table>

                            <br />

                            <center>
                                <button type="button" id="savePayrollBtn" class="hahaha">
                                    Save to Payroll
                                </button>
                            </center>
                        </div>
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

        window.addEventListener("load", function(){
            setTimeout(function(){
                document.getElementById("loading-screen").style.display = "none";
            }, 2000);
        });

        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });

        function fetchAttendance(employeeId, payPeriod) {
            fetch(`get_attendance.php?employee_id=${employeeId}&pay_period=${payPeriod}`)
                .then(res => res.json())
                .then(data => {
                    if(data.error) {
                        alert(data.error);
                    } else {
                        // Fill table or form
                        console.log(data);
                    }
                })
                .catch(err => console.error(err));
        }

        document.getElementById('savePayrollBtn').addEventListener('click', function() {

            const payrollId = <?= json_encode($salaryResult['payroll_id'] ?? null) ?>;

            if (!payrollId) {
                alert("Payroll ID not found. Please compute salary first.");
                return;
            }

            this.disabled = true;
            this.innerText = 'Saving...';

            fetch('save_payroll.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    payroll_action: 'mark_paid',
                    payroll_id: payrollId
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error("Network response was not OK");
                }
                return response.text(); // safer than direct json()
            })
            .then(text => {

                // Try parsing JSON safely
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("Invalid JSON:", text);
                    throw new Error("Invalid JSON response");
                }

                if (data.success) {
                    alert('Payroll saved! Status updated to Paid.');

                    // Safest approach → reload page
                    location.reload();

                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error("Fetch error:", error);
                alert('An error occurred while processing the request.');
            })
            .finally(() => {
                this.disabled = false;
                this.innerText = 'Save to Payroll';
            });

        });

        // Example: when employee or period changes
        document.getElementById('employee_select').addEventListener('change', function() {
            const empId = this.value;
            const period = document.getElementById('pay_period').value;
            fetchAttendance(empId, period);
        });

    </script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>