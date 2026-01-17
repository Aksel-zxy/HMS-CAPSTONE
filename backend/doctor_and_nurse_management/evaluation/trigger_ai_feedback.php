<?php
session_start();
include '../../../SQL/config.php';

$GEMINI_API_KEY = "CHANGE API KEY HERE"; 

if (isset($_SESSION['user_id'])) {
    
    $current_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['employee_id'])) {
    
    $current_id = $_SESSION['employee_id'];
} else {
   
    die("Unauthorized access. Please log in.");
}

$evaluation_id = $_POST['evaluation_id'] ?? null;

if (!$evaluation_id) {
    die("No evaluation ID provided.");
}

$sql = "SELECT average_score, performance_level, comments, ai_feedback 
        FROM evaluations 
        WHERE evaluation_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $evaluation_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "<script>alert('Evaluation not found.'); window.history.back();</script>";
    exit();
}

$q_sql = "SELECT q.criteria, a.score 
          FROM evaluation_answers a 
          JOIN evaluation_questions q ON a.question_id = q.question_id 
          WHERE a.evaluation_id = ?";

$q_stmt = $conn->prepare($q_sql);
$q_stmt->bind_param("i", $evaluation_id);
$q_stmt->execute();
$q_result = $q_stmt->get_result();

$details_list = "";
if ($q_result->num_rows > 0) {
    while ($row = $q_result->fetch_assoc()) {
        $details_list .= "- " . $row['criteria'] . ": " . $row['score'] . "/5\n";
    }
} else {
    $details_list = "No specific criteria details available.";
}

$average_score = $data['average_score'];
$performance_level = $data['performance_level'];
$comments = $data['comments'];

$prompt = "Act as a Senior Hospital HR Administrator. Write a short, constructive performance review summary (max 4 sentences) for a nurse based on this data:\n\n" .
    "OVERALL STATUS:\n" .
    "Final Score: $average_score/5.0\n" .
    "Rating Category: $performance_level\n" .
    "Doctor's Notes: '$comments'\n\n" .
    "DETAILED CRITERIA BREAKDOWN:\n" .
    "$details_list\n\n" .
    "INSTRUCTIONS:\n" . 
    "1. Address the nurse directly as 'you'.\n" .
    "2. Mention specific strengths based on high scores in the breakdown.\n" .
    "3. Gently point out areas for improvement based on lower scores (if any).\n" .
    "4. Be professional, encouraging, and specific.";

$ai_response = getGeminiFeedback($GEMINI_API_KEY, $prompt);

if ($ai_response) {
    $update_sql = "UPDATE evaluations SET ai_feedback = ? WHERE evaluation_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $ai_response, $evaluation_id);
    
    if ($update_stmt->execute()) {
        echo "<script>
                alert('AI Feedback Generated Successfully!');
                window.history.back(); 
              </script>";
    } else {
        echo "<script>alert('Database Error: Could not save AI feedback.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Error: AI failed to generate a response (Check API Key).'); window.history.back();</script>";
}

$conn->close();

function getGeminiFeedback($apiKey, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;

    $data = [ "contents" => [ [ "parts" => [ ["text" => $prompt] ] ] ] ];

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
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        return str_replace(['*', '#'], '', $decoded['candidates'][0]['content']['parts'][0]['text']);
    }
    return null;
}
?>