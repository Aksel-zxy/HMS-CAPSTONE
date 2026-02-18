<?php
include '../../SQL/config.php';

if (!isset($_SESSION['patient']) || $_SESSION['patient'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

function getComorbidities($conn, $patient_id) {

    $sql = "SELECT condition_name 
            FROM p_previous_medical_records
            WHERE patient_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $conditions = [];

    while ($row = $result->fetch_assoc()) {
        $conditions[] = $row['condition_name'];
    }

    if (empty($conditions)) {
        return "None";
    }

    return implode(", ", $conditions);
}


function predictLoS($age, $severity, $comorbidities, $admission_type) {

    $apiKey = "AIzaSyD1ydBto2H39tVDxw_4FJq4kCFAK39f2yA";

    $prompt = "
    You are a hospital AI specialized in predicting Length of Stay (LoS).

    Patient Information:
    - Age: $age
    - Severity Level (1=Mild, 2=Moderate, 3=Severe): $severity
    - Comorbidities: $comorbidities
    - Admission Type: $admission_type

    Predict hospital Length of Stay in DAYS.
    Return ONLY a number.
    ";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    $numeric = 0;

    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $rawOutput = $result['candidates'][0]['content']['parts'][0]['text'];
        // Extract numeric part
        $numeric = floatval(preg_replace('/[^0-9.]/', '', $rawOutput));
    }

    // If numeric is invalid or 0, use fallback formula
    if ($numeric <= 0) {
        $comorbidity_count = $comorbidities && $comorbidities != "None" ? count(explode(",", $comorbidities)) : 0;
        $numeric = max(1, round(0.05 * $age + 1.3 * $severity + 0.85 * $comorbidity_count + 1.7));
    }

    return $numeric; // always numeric, never a string
}