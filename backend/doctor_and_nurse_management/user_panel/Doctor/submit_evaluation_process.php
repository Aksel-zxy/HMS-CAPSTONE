<?php
session_start();
include '../../../../SQL/config.php';

$GEMINI_API_KEY = "AIzaSyCJSkGssDpAsH09J9Oyghn4ZW-EC_IVbKk";

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

if (!$evaluatee_id) {
    echo "<script>alert('Error: No nurse selected.'); window.location.href='perf_eval.php';</script>";
    exit();
}
if (empty($ratings)) {
    echo "<script>alert('Error: No ratings submitted.'); window.location.href='perf_eval.php';</script>";
    exit();
}

$total_score = 0;
$question_count = count($ratings);

foreach ($ratings as $score) {
    $total_score += intval($score);
}

$average_score = $question_count > 0 ? ($total_score / $question_count) : 0;
$average_score = round($average_score, 2);

$performance_level = "Pending";
if ($average_score >= 4.5) {
    $performance_level = "Excellent";
} elseif ($average_score >= 3.5) {
    $performance_level = "Good";
} elseif ($average_score >= 2.5) {
    $performance_level = "Satisfactory";
} elseif ($average_score >= 1.5) {
    $performance_level = "Needs Improvement";
} else {
    $performance_level = "Poor";
}

$ai_feedback = "AI feedback unavailable.";
if (!empty($GEMINI_API_KEY)) {
    $prompt = "Act as a Senior Hospital HR Administrator. Write a short, constructive performance review summary (max 3 sentences) for a nurse based on this data: " .
        "Score: $average_score/5.0. Rating: $performance_level. " .
        "Doctor's Notes: '$comments'. " .
        "Address the nurse directly as 'you'. Be professional and encouraging.";

    $ai_response = getGeminiFeedback($GEMINI_API_KEY, $prompt);

    if ($ai_response) {
        $ai_feedback = $ai_response;
    }
}

$conn->begin_transaction();

try {
    $sql_main = "INSERT INTO evaluations (evaluator_id, evaluatee_id, evaluation_date, total_score, average_score, performance_level, comments, ai_feedback) 
                 VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_main);
    $stmt->bind_param("iiddsss", $evaluator_id, $evaluatee_id, $total_score, $average_score, $performance_level, $comments, $ai_feedback);
    $stmt->execute();
    $evaluation_id = $conn->insert_id;
    $stmt->close();
    
    $sql_detail = "INSERT INTO evaluation_answers (evaluation_id, question_id, score) VALUES (?, ?, ?)";
    $stmt_detail = $conn->prepare($sql_detail);

    foreach ($ratings as $q_id => $score) {
        $stmt_detail->bind_param("iii", $evaluation_id, $q_id, $score);
        $stmt_detail->execute();
    }
    $stmt_detail->close();

    $conn->commit();

    echo "<script>
            alert('Evaluation Submitted!\\nRating: $performance_level\\n\\nAI Suggestion: $ai_feedback');
            window.location.href = 'perf_eval.php';
          </script>";
} catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='perf_eval.php';</script>";
}

$conn->close();

function getGeminiFeedback($apiKey, $prompt)
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;

    $data = array(
        "contents" => array(
            array(
                "parts" => array(
                    array("text" => $prompt)
                )
            )
        )
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return "System Notification: AI connection failed.";
    }
    curl_close($ch);

    $decoded = json_decode($response, true);
    if (isset($decoded['error'])) {
        $msg = $decoded['error']['message'];
        if (strpos($msg, 'quota') !== false || strpos($msg, '429') !== false) {
            return "Note: AI summary skipped due to high server traffic.";
        }
        return "AI Error: Service temporarily unavailable.";
    }

    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        return cleanText($decoded['candidates'][0]['content']['parts'][0]['text']);
    }

    return "No AI response generated.";
}

function cleanText($text)
{
    return str_replace(['*', '#'], '', $text);
}
?>