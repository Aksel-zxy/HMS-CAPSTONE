<?php
// Database connection
include '../../../../SQL/config.php';
// --- AUTOMATIC NURSE DETECTION LOGIC ---

$assigned_nurse = null; // Default to null

// 1. Get Today's details
$today_prefix = strtolower(date('D')); // Returns 'mon', 'tue', 'wed', etc.
$current_week_start = date('Y-m-d', strtotime('monday this week')); 
$room_col = $today_prefix . '_room_id'; // e.g., 'mon_room_id'

// 2. Find YOUR (Doctor's) Room for today
$doc_room_query = "SELECT $room_col as room_id FROM shift_scheduling 
                   WHERE employee_id = ? AND week_start = ?";
$stmt = $conn->prepare($doc_room_query);
$stmt->bind_param("is", $_SESSION['employee_id'], $current_week_start);
$stmt->execute();
$doc_res = $stmt->get_result();
$doc_schedule = $doc_res->fetch_assoc();

// 3. If you have a room today, find the Nurse in that same room
if ($doc_schedule && !empty($doc_schedule['room_id'])) {
    $my_room_id = $doc_schedule['room_id'];

    $nurse_query = "SELECT e.employee_id, e.first_name, e.last_name 
                    FROM shift_scheduling s
                    JOIN hr_employees e ON s.employee_id = e.employee_id
                    WHERE s.week_start = ? 
                    AND s.$room_col = ? 
                    AND e.profession = 'Nurse' 
                    LIMIT 1"; // Fetch 1 nurse
    
    $stmt = $conn->prepare($nurse_query);
    $stmt->bind_param("si", $current_week_start, $my_room_id);
    $stmt->execute();
    $nurse_res = $stmt->get_result();
    $assigned_nurse = $nurse_res->fetch_assoc();
}
// ---------------------------------------
?>