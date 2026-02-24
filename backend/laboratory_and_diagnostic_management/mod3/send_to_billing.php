<?php
session_start();
include '../../../SQL/config.php';

// Check if user is logged in
if (!isset($_SESSION['labtech']) || $_SESSION['labtech'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    
    // We expect schedules to be passed as JSON or an array
    $schedulesRaw = isset($_POST['schedules']) ? $_POST['schedules'] : '';
    $schedules = is_string($schedulesRaw) ? json_decode($schedulesRaw, true) : $schedulesRaw;

    if ($patient_id <= 0 || empty($schedules)) {
        echo json_encode(['success' => false, 'message' => 'Invalid patient data or no tests selected.']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1. Find an active Pending billing record, or create a new one
        $billing_id = null;
        $stmt_check_billing = $conn->prepare("SELECT billing_id FROM billing_records WHERE patient_id = ? AND status = 'Pending'");
        $stmt_check_billing->bind_param("i", $patient_id);
        $stmt_check_billing->execute();
        $res_check = $stmt_check_billing->get_result();
        
        if ($res_check->num_rows > 0) {
            $row = $res_check->fetch_assoc();
            $billing_id = $row['billing_id'];
        } else {
            // Create a new Pending billing record
            $stmt_insert_billing = $conn->prepare("INSERT INTO billing_records (patient_id, total_amount, grand_total, status) VALUES (?, 0.00, 0.00, 'Pending')");
            $stmt_insert_billing->bind_param("i", $patient_id);
            if (!$stmt_insert_billing->execute()) {
                throw new Exception("Failed to create billing record.");
            }
            $billing_id = $conn->insert_id;
        }

        $total_amount_added = 0;
        $dispatched_count = 0;

        // 2. Process each selected test
        foreach ($schedules as $schedule_id) {
            $schedule_id = (int)$schedule_id;

            // Check if this test was already sent to billing
            $stmt_check_dispatch = $conn->prepare("SELECT dispatchID FROM dl_billing_dispatch WHERE scheduleID = ?");
            $stmt_check_dispatch->bind_param("i", $schedule_id);
            $stmt_check_dispatch->execute();
            if ($stmt_check_dispatch->get_result()->num_rows > 0) {
                continue; // Skip, already billed
            }

            // Fetch the service associated with this schedule
            $stmt_get_service = $conn->prepare("
                SELECT s.serviceName, ds.serviceID, ds.price 
                FROM dl_schedule s 
                LEFT JOIN dl_services ds ON s.serviceName = ds.serviceName 
                WHERE s.scheduleID = ?
            ");
            $stmt_get_service->bind_param("i", $schedule_id);
            $stmt_get_service->execute();
            $res_service = $stmt_get_service->get_result();

            if ($res_service->num_rows > 0) {
                $service_data = $res_service->fetch_assoc();
                $service_id = $service_data['serviceID'] ? $service_data['serviceID'] : 0;
                $price = $service_data['price'] ? $service_data['price'] : 0;

                // Insert into billing_items
                $stmt_insert_item = $conn->prepare("
                    INSERT INTO billing_items (billing_id, patient_id, service_id, quantity, unit_price, total_price, finalized) 
                    VALUES (?, ?, ?, 1, ?, ?, 0)
                ");
                $stmt_insert_item->bind_param("iiidd", $billing_id, $patient_id, $service_id, $price, $price);
                if (!$stmt_insert_item->execute()) {
                    throw new Exception("Failed to insert billing item for schedule $schedule_id.");
                }

                $total_amount_added += $price;

                // Insert into dl_billing_dispatch
                $stmt_insert_dispatch = $conn->prepare("
                    INSERT INTO dl_billing_dispatch (patientID, scheduleID, dispatched_by, billing_record_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_insert_dispatch->bind_param("iiii", $patient_id, $schedule_id, $user_id, $billing_id);
                if (!$stmt_insert_dispatch->execute()) {
                    throw new Exception("Failed to log dispatch for schedule $schedule_id.");
                }

                $dispatched_count++;
            }
        }

        // 3. Update the total amount in billing_records IF we added new items
        if ($dispatched_count > 0 && $total_amount_added > 0) {
            $stmt_update_billing = $conn->prepare("
                UPDATE billing_records 
                SET total_amount = total_amount + ?, 
                    grand_total = grand_total + ?, 
                    balance = balance + ?
                WHERE billing_id = ?
            ");
            $stmt_update_billing->bind_param("dddi", $total_amount_added, $total_amount_added, $total_amount_added, $billing_id);
            $stmt_update_billing->execute();
        }

        $conn->commit();

        if ($dispatched_count > 0) {
            echo json_encode(['success' => true, 'message' => "Successfully sent $dispatched_count test(s) to billing!"]);
        } else {
            echo json_encode(['success' => true, 'message' => "Selected tests were already sent to billing or could not be found."]);
        }

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
