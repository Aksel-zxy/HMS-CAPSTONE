<?php
include '../../../SQL/config.php';

// ------------------ PaySlip Viewing Class ------------------
class PayrollReports {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get all payrolls marked as 'Pending', optionally filtered by date range
     *
     * @param string $start Start date (YYYY-MM-DD)
     * @param string $end End date (YYYY-MM-DD)
     * @return array
     */
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
        $types = "i";

        if (!empty($start) && !empty($end)) {
            $sql .= " AND p.pay_period_start >= ? AND p.pay_period_end <= ?";
            $params[] = $start;
            $params[] = $end;
            $types .= "ss";
        }

        $sql .= " ORDER BY p.date_generated DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $payrolls = [];
        while ($row = $result->fetch_assoc()) {
            $row['gross_pay'] = (float) ($row['gross_pay'] ?? 0);
            $row['total_deductions'] = (float) ($row['total_deductions'] ?? 0);
            $row['net_pay'] = (float) ($row['net_pay'] ?? 0);
            $payrolls[] = $row;
        }

        return $payrolls;
    }

    /**
     * Get payroll summary totals
     *
     * @param array $payrolls Array of payroll rows
     * @return array ['total_gross' => , 'total_deductions' => , 'total_net' => ]
     */
    public function getSummaryTotals($payrolls) {
        $totalGross = $totalDeductions = $totalNet = 0;

        foreach ($payrolls as $row) {
            $totalGross += $row['gross_pay'];
            $totalDeductions += $row['total_deductions'];
            $totalNet += $row['net_pay'];
        }

        return [
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet
        ];
    }

    /**
     * Format totals for display
     *
     * @param float $amount
     * @return string
     */
    public function formatCurrency($amount) {
        return number_format($amount, 2);
    }
}

// ------------------ Access Control ------------------
if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Laboratorist') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['employee_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// Fetch user details from database
$query = "SELECT * FROM hr_employees WHERE employee_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['employee_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

// ------------------ Initialize PayrollReports ------------------
$report = new PayrollReports($conn);

// Get filter values from GET request
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

// Fetch payrolls and totals
$employeeId = $_SESSION['employee_id'];
$payrolls = $report->getPayrolls($employeeId, $start, $end);
$totals = $report->getSummaryTotals($payrolls);

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | User Panel</title>
    <link rel="shortcut icon" href="../../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/CSS/super.css">
     <link rel="stylesheet" href="payslip_viewing.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <li class="sidebar-item">
                <a href="user_lab.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="sample_processing.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill-up" viewBox="0 0 16 16">
                    <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.354-5.854 1.5 1.5a.5.5 0 0 1-.708.708L13 11.707V14.5a.5.5 0 0 1-1 0v-2.793l-.646.647a.5.5 0 0 1-.708-.708l1.5-1.5a.5.5 0 0 1 .708 0M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                    <path d="M2 13c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"/>
                    </svg>
                    <span style="font-size: 18px;">Sample Process</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="leave_request.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-walking" viewBox="0 0 16 16">
                        <path d="M9.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M6.44 3.752A.75.75 0 0 1 7 3.5h1.445c.742 0 1.32.643 1.243 1.38l-.43 4.083a1.8 1.8 0 0 1-.088.395l-.318.906.213.242a.8.8 0 0 1 .114.175l2 4.25a.75.75 0 1 1-1.357.638l-1.956-4.154-1.68-1.921A.75.75 0 0 1 6 8.96l.138-2.613-.435.489-.464 2.786a.75.75 0 1 1-1.48-.246l.5-3a.75.75 0 0 1 .18-.375l2-2.25Z"/>
                        <path d="M6.25 11.745v-1.418l1.204 1.375.261.524a.8.8 0 0 1-.12.231l-2.5 3.25a.75.75 0 1 1-1.19-.914zm4.22-4.215-.494-.494.205-1.843.006-.067 1.124 1.124h1.44a.75.75 0 0 1 0 1.5H11a.75.75 0 0 1-.531-.22Z"/>
                    </svg>
                    <span style="font-size: 18px;">Leave Request</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="payslip_viewing.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text-fill" viewBox="0 0 16 16">
                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M4.5 9a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1zM4 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 1 0-1h4a.5.5 0 0 1 0 1z"/>
                    </svg>
                    <span style="font-size: 18px;">Payslip Viewing</span>
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
                        <span class="username ml-1 me-2"><?php echo $user['first_name']; ?> <?php echo $user['last_name']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['last_name']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="payrollreports">
                <p style="text-align: center; font-size: 35px; font-weight: bold; padding-bottom: 20px; color: #0047ab;">Payroll Reports</p>

                <!-- FILTER FORM -->
                <form method="GET" class="payrollreports-nav-inline">
                    <label>Start Date:</label>
                    <input type="date" name="start" value="<?= htmlspecialchars($start) ?>">

                    <label>End Date:</label>
                    <input type="date" name="end" value="<?= htmlspecialchars($end) ?>">

                    <button type="submit">Filter</button>
                    <a href="payslip_viewing.php" style="text-decoration:none; padding:8px 16px; background:#404040; color:white; border-radius:5px;">Reset</a>
                </form>

                <!-- PAYROLL TABLE -->
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
                                            <?= $row['pay_period_start'] ?>
                                            <br />
                                            to
                                            <br />
                                            <?= $row['pay_period_end'] ?>
                                        </td>
                                        <td><?= number_format($row['gross_pay'], 2) ?></td>
                                        <td><?= number_format($row['total_deductions'], 2) ?></td>
                                        <td><strong><?= number_format($row['net_pay'], 2) ?></strong></td>
                                        <td><?= $row['date_generated'] ?></td>
                                        <td>
                                            <a href="view_payslip.php?payroll_id=<?= $row['payroll_id'] ?>" target="_blank" class="view-link"> View Payslip </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align:center;">No payroll records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="../../assets/Bootstrap/all.min.js"></script>
    <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../../assets/Bootstrap/jq.js"></script>
</body>

</html>