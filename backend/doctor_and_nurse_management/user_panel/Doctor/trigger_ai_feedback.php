<?php
session_start();
include '../../../../SQL/config.php';

// PUT YOUR API KEY HERE
$GEMINI_API_KEY = "YOUR_ACTUAL_API_KEY_HERE"; 

if (!isset($_SESSION['employee_id']) || $_SESSION['profession'] !== 'Doctor') {
    die("Unauthorized access.");
}

// Get the Evaluation ID to process
$evaluation_id = $_POST['evaluation_id'] ?? null;
$evaluator_id = $_SESSION['employee_id'];

if (!$evaluation_id) {
    die("No evaluation ID provided.");
}

// 1. Fetch the existing evaluation data from DB
// We also check 'evaluator_id' to ensure doctors can only generate AI for their own submissions
$sql = "SELECT average_score, performance_level, comments, ai_feedback 
        FROM evaluations 
        WHERE evaluation_id = ? AND evaluator_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $evaluation_id, $evaluator_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "<script>alert('Evaluation not found or access denied.'); window.location.href='perf_eval.php';</script>";
    exit();
}

// 2. Generate AI Prompt
$average_score = $data['average_score'];
$performance_level = $data['performance_level'];
$comments = $data['comments'];

$prompt = "Act as a Senior Hospital HR Administrator. Write a short, constructive performance review summary (max 3 sentences) for a nurse based on this data: " .
    "Score: $average_score/5.0. Rating: $performance_level. " .
    "Doctor's Notes: '$comments'. " .
    "Address the nurse directly as 'you'. Be professional and encouraging.";

// 3. Call Gemini API
$ai_response = getGeminiFeedback($GEMINI_API_KEY, $prompt);

// 4. Update the Database with the result
if ($ai_response) {
    $update_sql = "UPDATE evaluations SET ai_feedback = ? WHERE evaluation_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $ai_response, $evaluation_id);
    
    if ($update_stmt->execute()) {
        echo "<script>
                alert('AI Feedback Generated Successfully!');
                window.location.href='perf_eval.php';
              </script>";
    } else {
        echo "<script>alert('Database Error: Could not save AI feedback.'); window.location.href='perf_eval.php';</script>";
    }
} else {
    echo "<script>alert('Error: AI failed to generate a response.'); window.location.href='perf_eval.php';</script>";
}

$conn->close();

// --- GEMINI FUNCTION ---
function getGeminiFeedback($apiKey, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;

    $data = [
        "contents" => [
            [ "parts" => [ ["text" => $prompt] ] ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) return null;
    curl_close($ch);

    $decoded = json_decode($response, true);
    
    // Check for API errors
    if (isset($decoded['error'])) return null;

    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        return str_replace(['*', '#'], '', $decoded['candidates'][0]['content']['parts'][0]['text']);
    }

    return null;
}
?>