<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
session_start();

require '../../SQL/config.php';
require_once 'classes/medicine.php';

date_default_timezone_set('Asia/Manila');

$schedule_id = intval($_POST['schedule_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

header('Content-Type: application/json');

if (!$schedule_id) {
    echo json_encode(['error' => 'Schedule ID is required.']);
    exit;
}

if (!$user_id) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

try {
    $conn->begin_transaction();
    $medicineObj = new Medicine($conn);

    // ===============================
    // Fetch schedule
    // ===============================
    $scheduleRes = $conn->query("
        SELECT sm.*, m.med_name, m.stock_quantity
        FROM scheduled_medications sm
        JOIN pharmacy_inventory m ON sm.med_id = m.med_id
        WHERE sm.schedule_id = {$schedule_id}
        FOR UPDATE
    ");

    if ($scheduleRes->num_rows === 0) {
        throw new Exception("Schedule not found.");
    }

    $schedule = $scheduleRes->fetch_assoc();

    // ===============================
    // Count doses already given
    // ===============================
    $logRes = $conn->query("
        SELECT COUNT(*) as total_given
        FROM scheduled_medication_logs
        WHERE schedule_id = {$schedule_id}
    ");
    $doses_given = (int)$logRes->fetch_assoc()['total_given'];

    // ===============================
    // Compute total doses dynamically
    // ===============================
    $frequency = strtolower(trim($schedule['frequency']));
    $duration = (int)$schedule['duration_days'];
    $doses_per_day = 1;

    // 1️⃣ Specific times of day (e.g., 9 AM & 9 PM)
    if (preg_match_all('/(\d{1,2}\s*(am|pm))/i', $frequency, $matches)) {
        $doses_per_day = count($matches[0]);

        // 2️⃣ Interval-based (e.g., every 6 hours)
    } elseif (preg_match('/every\s+(\d+)\s*hours?/', $frequency, $matches)) {
        $hours = (int)$matches[1];
        $doses_per_day = ($hours > 0 && $hours <= 24) ? floor(24 / $hours) : 1;

        // 3️⃣ X times a day (e.g., 5x a day)
    } elseif (preg_match('/^(\d+)\s*(x|times)?\s*(a day|per day)?$/i', $frequency, $matches)) {
        $doses_per_day = (int)$matches[1];

        // 4️⃣ Fallback phrases
    } else {
        switch ($frequency) {
            case 'twice a day':
                $doses_per_day = 2;
                break;
            case 'three times a day':
                $doses_per_day = 3;
                break;
            default:
                $doses_per_day = 1;
                break;
        }
    }

    $total_doses = $doses_per_day * $duration;

    if ($doses_given >= $total_doses) {
        throw new Exception("All doses already completed.");
    }

    // ===============================
    // Time validation (5 min early rule)
    // ===============================
    $interval_hours = ($doses_per_day > 0) ? 24 / $doses_per_day : 24;
    $start_timestamp = strtotime($schedule['start_date']);
    $next_dose_time = strtotime("+" . ($doses_given * $interval_hours) . " hours", $start_timestamp);
    $allow_time = strtotime("-5 minutes", $next_dose_time);
    $current_time = time();
    if ($current_time < $allow_time) {
        throw new Exception("Not yet time to dispense this dose.");
    }

    // ===============================
    // Stock check & FIFO with expiry
    // ===============================
    $batchRes = $conn->query("
        SELECT batch_id, stock_quantity, expiry_date
        FROM pharmacy_stock_batches
        WHERE med_id = {$schedule['med_id']}
          AND stock_quantity > 0
          AND expiry_date >= CURDATE()
        ORDER BY expiry_date ASC, batch_id ASC
        LIMIT 1
    ");

    if ($batchRes->num_rows === 0) {
        throw new Exception("No available stock (All Stock Expired) for {$schedule['med_name']}.");
    }

    $batch = $batchRes->fetch_assoc();
    $batch_id = $batch['batch_id'];

    // ===============================
    // Insert log
    // ===============================
    $stmt = $conn->prepare("
        INSERT INTO scheduled_medication_logs 
        (schedule_id, quantity_given, given_at)
        VALUES (?, 1, NOW())
    ");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();

    // ===============================
    // Deduct stock from batch & main inventory
    // ===============================
    $stmt = $conn->prepare("
        UPDATE pharmacy_stock_batches
        SET stock_quantity = stock_quantity - 1
        WHERE batch_id = ?
    ");
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();

    $conn->query("
        UPDATE pharmacy_inventory
        SET stock_quantity = stock_quantity - 1
        WHERE med_id = {$schedule['med_id']}
    ");

    $doses_given++;

    // ===============================
    // Update schedule status
    // ===============================
    if ($doses_given == 1) {
        $conn->query("UPDATE scheduled_medications SET status='ongoing' WHERE schedule_id = {$schedule_id}");
    }
    if ($doses_given >= $total_doses) {
        $conn->query("UPDATE scheduled_medications SET status='completed' WHERE schedule_id = {$schedule_id}");
    }

    $conn->commit();

    echo json_encode([
        'success' => "Dose dispensed successfully.",
        'doses_given' => $doses_given,
        'total_doses' => $total_doses,
        'status' => ($doses_given >= $total_doses) ? 'completed' : 'ongoing'
    ]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
