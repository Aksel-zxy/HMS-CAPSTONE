<?php
session_start();
include '../../../../SQL/config.php';

if (!isset($_SESSION['employee_id']) || $_SESSION['profession'] !== 'Doctor') {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request method.");
}

$evaluator_id = $_SESSION['employee_id'];
$evaluatee_id = $_POST['evaluatee_id'] ?? null;
$comments = $_POST['comments'] ?? 'No specific comments provided.';
$ratings = $_POST['ratings'] ?? [];

// Basic Validation
if (!$evaluatee_id) {
    echo "<script>alert('Error: No nurse selected.'); window.location.href='perf_eval.php';</script>";
    exit();
}
if (empty($ratings)) {
    echo "<script>alert('Error: No ratings submitted.'); window.location.href='perf_eval.php';</script>";
    exit();
}

// 1. Calculate Score
$total_score = 0;
$question_count = count($ratings);

foreach ($ratings as $score) {
    $total_score += intval($score);
}

$average_score = $question_count > 0 ? ($total_score / $question_count) : 0;
$average_score = round($average_score, 2);

// 2. Determine Performance Level
$performance_level = "Pending";
if ($average_score >= 4.5) $performance_level = "Excellent";
elseif ($average_score >= 3.5) $performance_level = "Good";
elseif ($average_score >= 2.5) $performance_level = "Satisfactory";
elseif ($average_score >= 1.5) $performance_level = "Needs Improvement";
else $performance_level = "Poor";

// 3. Set Default AI Status (We do NOT generate it here anymore)
$ai_feedback_placeholder = "Pending Generation..."; 

$conn->begin_transaction();

try {
    // Insert Main Evaluation
    $sql_main = "INSERT INTO evaluations (evaluator_id, evaluatee_id, evaluation_date, total_score, average_score, performance_level, comments, ai_feedback) 
                 VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_main);
    $stmt->bind_param("iiddsss", $evaluator_id, $evaluatee_id, $total_score, $average_score, $performance_level, $comments, $ai_feedback_placeholder);
    $stmt->execute();
    $evaluation_id = $conn->insert_id;
    $stmt->close();
    
    // Insert Details
    $sql_detail = "INSERT INTO evaluation_answers (evaluation_id, question_id, score) VALUES (?, ?, ?)";
    $stmt_detail = $conn->prepare($sql_detail);

    foreach ($ratings as $q_id => $score) {
        $stmt_detail->bind_param("iii", $evaluation_id, $q_id, $score);
        $stmt_detail->execute();
    }
    $stmt_detail->close();

    $conn->commit();

    // Success - Redirect back to main page
    echo "<script>
            alert('Evaluation Saved Successfully!');
            window.location.href = 'perf_eval.php';
          </script>";

} catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='perf_eval.php';</script>";
}

$conn->close();
?>