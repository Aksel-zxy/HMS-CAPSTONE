<?php
session_start(); // ✅ Must be first

include '../../SQL/config.php'; // ✅ Two levels up: billing_and_insurance_management → backend → HMS-CAPSTONE/SQL/

// ------------------ PaySlip Viewing Class ------------------
class PayrollReports {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getPayrolls($employeeId, $start = '', $end = '') {
        $sql = "
            SELECT 
                p.payroll_id,
                e.employee_id,
                TRIM(CONCAT(
                    COALESCE(e.first_name, ''), ' ',
                    COALESCE(e.middle_name, ''), ' ',
                    COALESCE(e.last_name, ''), ' ',
                    COALESCE(e.suffix_name, '')
                )) AS employee_name,
                e.profession,
                e.department,
                p.pay_period_start,
                p.pay_period_end,
                p.days_worked,
                p.overtime_hours,
                p.basic_pay,
                p.overtime_pay,
                p.allowances,
                p.bonuses,
                p.thirteenth_month,
                p.undertime_deduction,
                p.sss_deduction,
                p.philhealth_deduction,
                p.pagibig_deduction,
                p.absence_deduction,
                p.gross_pay,
                p.total_deductions,
                p.net_pay,
                p.disbursement_method,
                p.date_generated
            FROM hr_payroll p
            JOIN hr_employees e ON p.employee_id = e.employee_id
            WHERE p.status = 'Pending' AND e.employee_id = ?
        ";

        $params = [$employeeId];
        $types  = "i";

        if (!empty($start) && !empty($end)) {
            $sql    .= " AND p.pay_period_start >= ? AND p.pay_period_end <= ?";
            $params[] = $start;
            $params[] = $end;
            $types  .= "ss";
        }

        $sql .= " ORDER BY p.date_generated DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $payrolls = [];
        while ($row = $result->fetch_assoc()) {
            $row['gross_pay']        = (float)($row['gross_pay']        ?? 0);
            $row['total_deductions'] = (float)($row['total_deductions'] ?? 0);
            $row['net_pay']          = (float)($row['net_pay']          ?? 0);
            $payrolls[] = $row;
        }

        return $payrolls;
    }

    public function getSummaryTotals($payrolls) {
        $totalGross = $totalDeductions = $totalNet = 0;
        foreach ($payrolls as $row) {
            $totalGross        += $row['gross_pay'];
            $totalDeductions   += $row['total_deductions'];
            $totalNet          += $row['net_pay'];
        }
        return [
            'total_gross'       => $totalGross,
            'total_deductions'  => $totalDeductions,
            'total_net'         => $totalNet
        ];
    }

    public function formatCurrency($amount) {
        return number_format($amount, 2);
    }
}

// ─────────────────────────────────────────────
// ACCESS CONTROL
// ─────────────────────────────────────────────
// FIX: Original code required $_SESSION['profession'] === 'Doctor',
// blocking all other roles. Now we accept any authenticated session
// that has either employee_id (HR portal) or user_id (billing portal).
// ─────────────────────────────────────────────
$session_employee_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? null;

if (!$session_employee_id) {
    header('Location: ../../login.php');
    exit();
}

// Fetch user/employee details
$stmt = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
$stmt->bind_param("i", $session_employee_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();

if (!$user) {
    // Authenticated but no HR employee record — show friendly error
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payslip Viewing — Access Error</title>
        <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
        <style>
            body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f5f6f7; font-family:'Segoe UI',sans-serif; }
            .err-card { background:#fff; border-radius:12px; padding:2.5rem 2rem; box-shadow:0 4px 24px rgba(0,0,0,.1); max-width:420px; width:100%; text-align:center; }
            .err-icon { font-size:3rem; margin-bottom:1rem; }
            h2 { font-size:1.3rem; font-weight:700; color:#1a2640; margin-bottom:.5rem; }
            p  { color:#7a8fb5; font-size:.9rem; margin-bottom:1.5rem; }
            a  { display:inline-block; background:#0047ab; color:#fff; padding:.6rem 1.4rem; border-radius:8px; text-decoration:none; font-size:.9rem; }
        </style>
    </head>
    <body>
        <div class="err-card">
            <div class="err-icon">⚠️</div>
            <h2>Employee Record Not Found</h2>
            <p>Your account is authenticated but no matching employee profile was found in the HR system.
               Please contact your HR administrator to link your account.</p>
            <a href="../../login.php">Back to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ─────────────────────────────────────────────
// Initialize report & filters
// ─────────────────────────────────────────────
$report     = new PayrollReports($conn);
$start      = $_GET['start'] ?? '';
$end        = $_GET['end']   ?? '';
$employeeId = (int)$user['employee_id'];
$payrolls   = $report->getPayrolls($employeeId, $start, $end);
$totals     = $report->getSummaryTotals($payrolls);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Payslip Viewing</title>
    <link rel="shortcut icon" href="../../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/CSS/super.css">
    <link rel="stylesheet" href="../../assets/CSS/payslip_viewing.css">
</head>

<body>
<div class="d-flex">

    <!----- Sidebar ----->
    <aside id="sidebar" class="sidebar-toggle">
        <div class="sidebar-logo mt-3">
            <img src="../../assets/image/logo-dark.png" width="90px" height="20px" alt="Logo">
        </div>

        <div class="menu-title">Navigation</div>

        <li class="sidebar-item">
            <a href="leave_request.php" class="sidebar-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-walking" viewBox="0 0 16 16">
                    <path d="M9.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M6.44 3.752A.75.75 0 0 1 7 3.5h1.445c.742 0 1.32.643 1.243 1.38l-.43 4.083a1.8 1.8 0 0 1-.088.395l-.318.906.213.242a.8.8 0 0 1 .114.175l2 4.25a.75.75 0 1 1-1.357.638l-1.956-4.154-1.68-1.921A.75.75 0 0 1 6 8.96l.138-2.613-.435.489-.464 2.786a.75.75 0 1 1-1.48-.246l.5-3a.75.75 0 0 1 .18-.375l2-2.25Z"/>
                    <path d="M6.25 11.745v-1.418l1.204 1.375.261.524a.8.8 0 0 1-.12.231l-2.5 3.25a.75.75 0 1 1-1.19-.914zm4.22-4.215-.494-.494.205-1.843.006-.067 1.124 1.124h1.44a.75.75 0 0 1 0 1.5H11a.75.75 0 0 1-.531-.22Z"/>
                </svg>
                <span style="font-size:18px;">Leave Request</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="payslip_viewing.php" class="sidebar-link" aria-current="page">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text-fill" viewBox="0 0 16 16">
                    <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M4.5 9a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1zM4 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 1 0-1h4a.5.5 0 0 1 0 1z"/>
                </svg>
                <span style="font-size:18px;">Payslip Viewing</span>
            </a>
        </li>
    </aside>
    <!----- End Sidebar ----->

    <!----- Main Content ----->
    <div class="main">

        <!-- Topbar -->
        <div class="topbar">
            <div class="toggle">
                <button class="toggler-btn" type="button" aria-label="Toggle sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-list-ul" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
                    </svg>
                </button>
            </div>
            <div class="logo">
                <div class="dropdown d-flex align-items-center">
                    <span class="username ml-1 me-2">
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                    </span>
                    <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton"
                        style="min-width:200px;padding:10px;border-radius:5px;box-shadow:0 4px 6px rgba(0,0,0,.1);background:#fff;">
                        <li style="margin-bottom:8px;font-size:14px;color:#555;">
                            Welcome <strong style="color:#007bff;"><?= htmlspecialchars($user['last_name']) ?></strong>!
                        </li>
                        <li>
                            <a class="dropdown-item" href="../../../logout.php"
                               onclick="return confirm('Are you sure you want to log out?');"
                               style="font-size:14px;color:#007bff;padding:8px 12px;border-radius:4px;">
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- End Topbar -->

        <!-- ── Payroll Reports ── -->
        <div class="payrollreports">
            <p style="text-align:center;font-size:35px;font-weight:bold;padding-bottom:20px;color:#0047ab;">
                Payroll Reports
            </p>

            <!-- Filter Form -->
            <form method="GET" class="payrollreports-nav-inline">
                <label>Start Date:</label>
                <input type="date" name="start" value="<?= htmlspecialchars($start) ?>">

                <label>End Date:</label>
                <input type="date" name="end" value="<?= htmlspecialchars($end) ?>">

                <button type="submit">Filter</button>
                <a href="payslip_viewing.php"
                   style="text-decoration:none;padding:8px 16px;background:#404040;color:#fff;border-radius:5px;">
                    Reset
                </a>
            </form>

            <!-- Payroll Table -->
            <div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Position</th>
                            <th>Department</th>
                            <th>Pay Period</th>
                            <th>Gross Pay</th>
                            <th>Total Deductions</th>
                            <th>Net Pay</th>
                            <th>Date Paid</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payrolls)): ?>
                            <?php $i = 1; foreach ($payrolls as $row): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                    <td><?= htmlspecialchars($row['profession']) ?></td>
                                    <td><?= htmlspecialchars($row['department']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['pay_period_start']) ?>
                                        <br>to<br>
                                        <?= htmlspecialchars($row['pay_period_end']) ?>
                                    </td>
                                    <td><?= number_format($row['gross_pay'],        2) ?></td>
                                    <td><?= number_format($row['total_deductions'], 2) ?></td>
                                    <td><strong><?= number_format($row['net_pay'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars($row['date_generated']) ?></td>
                                    <td>
                                        <a href="view_payslip.php?payroll_id=<?= (int)$row['payroll_id'] ?>"
                                           target="_blank" class="view-link">
                                            View Payslip
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align:center;color:#999;padding:1.5rem;">
                                    No payroll records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                    <!-- Summary Totals Footer -->
                    <?php if (!empty($payrolls)): ?>
                    <tfoot>
                        <tr style="font-weight:700;background:#f0f4fb;">
                            <td colspan="5" style="text-align:right;padding:.75rem 1rem;">Totals:</td>
                            <td><?= $report->formatCurrency($totals['total_gross']) ?></td>
                            <td><?= $report->formatCurrency($totals['total_deductions']) ?></td>
                            <td><?= $report->formatCurrency($totals['total_net']) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <!-- End Payroll Reports -->

    </div>
    <!----- End Main Content ----->
</div>

<script>
    const toggler = document.querySelector('.toggler-btn');
    if (toggler) {
        toggler.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
    }
</script>
<script src="../../assets/Bootstrap/all.min.js"></script>
<script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
<script src="../../assets/Bootstrap/fontawesome.min.js"></script>
<script src="../../assets/Bootstrap/jq.js"></script>
</body>
</html>