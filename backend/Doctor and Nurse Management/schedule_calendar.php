<?php
include '../../SQL/config.php';

// Filters
$role = $_GET['role'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';
$view = $_GET['view'] ?? 'week'; // 'week' or 'day'

// Build query with filters
$query = "SELECT s.schedule_id, s.employee_id, s.week_start, 
                 s.mon_start, s.mon_end, s.mon_status,
                 s.tue_start, s.tue_end, s.tue_status,
                 s.wed_start, s.wed_end, s.wed_status,
                 s.thu_start, s.thu_end, s.thu_status,
                 s.fri_start, s.fri_end, s.fri_status,
                 s.sat_start, s.sat_end, s.sat_status,
                 s.sun_start, s.sun_end, s.sun_status,
                 e.first_name, e.last_name, e.role, e.department
          FROM shift_scheduling s
          JOIN hr_employees e ON s.employee_id = e.employee_id
          WHERE 1=1";

if ($role !== '') {
    $query .= " AND e.role = '" . $conn->real_escape_string($role) . "'";
}
if ($department !== '') {
    $query .= " AND e.department = '" . $conn->real_escape_string($department) . "'";
}
if ($status !== '') {
    // status filter applies to any day, so we need to check all *_status columns
    $query .= " AND (
        s.mon_status = '" . $conn->real_escape_string($status) . "' OR
        s.tue_status = '" . $conn->real_escape_string($status) . "' OR
        s.wed_status = '" . $conn->real_escape_string($status) . "' OR
        s.thu_status = '" . $conn->real_escape_string($status) . "' OR
        s.fri_status = '" . $conn->real_escape_string($status) . "' OR
        s.sat_status = '" . $conn->real_escape_string($status) . "' OR
        s.sun_status = '" . $conn->real_escape_string($status) . "'
    )";
}

$result = $conn->query($query);

$events = [];
$days = [
    'Monday'    => ['col_start' => 'mon_start', 'col_end' => 'mon_end', 'col_status' => 'mon_status', 'date' => '2025-08-18'],
    'Tuesday'   => ['col_start' => 'tue_start', 'col_end' => 'tue_end', 'col_status' => 'tue_status', 'date' => '2025-08-19'],
    'Wednesday' => ['col_start' => 'wed_start', 'col_end' => 'wed_end', 'col_status' => 'wed_status', 'date' => '2025-08-20'],
    'Thursday'  => ['col_start' => 'thu_start', 'col_end' => 'thu_end', 'col_status' => 'thu_status', 'date' => '2025-08-21'],
    'Friday'    => ['col_start' => 'fri_start', 'col_end' => 'fri_end', 'col_status' => 'fri_status', 'date' => '2025-08-22'],
    'Saturday'  => ['col_start' => 'sat_start', 'col_end' => 'sat_end', 'col_status' => 'sat_status', 'date' => '2025-08-23'],
    'Sunday'    => ['col_start' => 'sun_start', 'col_end' => 'sun_end', 'col_status' => 'sun_status', 'date' => '2025-08-24'],
];

while ($row = $result->fetch_assoc()) {
    $full_name = $row['first_name'] . ' ' . $row['last_name'];
    foreach ($days as $day => $info) {
        $start = $row[$info['col_start']];
        $end = $row[$info['col_end']];
        $status = $row[$info['col_status']];
        if ($start && $end && $status) {
            $events[] = [
                'title' => $full_name . " (" . $row['role'] . " - " . $status . ")",
                'start' => $info['date'] . "T" . $start,
                'end'   => $info['date'] . "T" . $end,
                'groupId' => $row['department'],
                'extendedProps' => [
                    'department' => $row['department'],
                    'role' => $row['role'],
                    'status' => $status,
                    'staff' => $full_name
                ]
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Schedule Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .filters { margin-bottom: 20px; }
        .filters select { margin-right: 10px; padding: 5px; }
        .view-switch { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h2>Doctor/Nurse Schedule Viewer</h2>
    <form method="GET" class="filters">
        <label>Role:</label>
        <select name="role">
            <option value="">All</option>
            <option value="Doctor" <?= $role=='Doctor'?'selected':'' ?>>Doctor</option>
            <option value="Nurse" <?= $role=='Nurse'?'selected':'' ?>>Nurse</option>
        </select>
        <label>Department:</label>
        <select name="department">
            <option value="">All</option>
            <option value="Surgery" <?= $department=='Surgery'?'selected':'' ?>>Surgery</option>
            <option value="Pediatrics" <?= $department=='Pediatrics'?'selected':'' ?>>Pediatrics</option>
            <option value="ER" <?= $department=='ER'?'selected':'' ?>>ER</option>
        </select>
        <label>Status:</label>
        <select name="status">
            <option value="">All</option>
            <option value="On Duty" <?= $status=='On Duty'?'selected':'' ?>>On Duty</option>
            <option value="Off Duty" <?= $status=='Off Duty'?'selected':'' ?>>Off Duty</option>
            <option value="Leave" <?= $status=='Leave'?'selected':'' ?>>Leave</option>
            <option value="Sick" <?= $status=='Sick'?'selected':'' ?>>Sick</option>
        </select>
        <span class="view-switch">
            <label><input type="radio" name="view" value="week" <?= $view=='week'?'checked':'' ?>> Weekly by Staff</label>
            <label><input type="radio" name="view" value="day" <?= $view=='day'?'checked':'' ?>> Daily by Department</label>
        </span>
        <button type="submit">Apply</button>
    </form>
    <div id="calendar" style="max-width: 1000px; margin: auto;"></div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var initialView = 'timeGridWeek';
        <?php if ($view == 'day'): ?>
            initialView = 'timeGridDay';
        <?php endif; ?>
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: initialView,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: <?php echo json_encode($events); ?>,
            eventClick: function(info) {
                var props = info.event.extendedProps;
                alert(
                    "Staff: " + props.staff +
                    "\nRole: " + props.role +
                    "\nDepartment: " + props.department +
                    "\nStatus: " + props.status +
                    "\nStart: " + info.event.start.toLocaleString() +
                    "\nEnd: " + info.event.end.toLocaleString()
                );
            }
        });
        calendar.render();
    });
    </script>
</body>
</html>

















