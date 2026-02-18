<?php
require '../../../SQL/config.php';

// Set header for JSON response
header('Content-Type: application/json');

// ----------------------
// Get applicant ID
// ----------------------
$input = json_decode(file_get_contents('php://input'), true);
$applicantId = 0;

// Check POST JSON first
if ($input && isset($input['applicant_id'])) {
    $applicantId = intval($input['applicant_id']);
}
// Fallback to GET for testing via browser
elseif (isset($_GET['id'])) {
    $applicantId = intval($_GET['id']);
}

if (!$applicantId) {
    echo json_encode(['error' => 'No applicant ID provided']);
    exit;
}

// ----------------------
// Fetch Resume PDF & metadata
// ----------------------
$sql = "
SELECT d.file_blob, d.document_type, a.role, a.specialization
FROM hr_applicant_documents d
JOIN hr_applicant a ON a.applicant_id = d.applicant_id
WHERE d.applicant_id = ?
AND d.document_type = 'Resume'
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $applicantId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo json_encode(['error' => 'Applicant or Resume not found']);
    exit;
}

// ----------------------
// Gemini AI function
// ----------------------
function analyzeResumeAI($data) {
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent";
    $apiKey = getenv('MARWIN_KEY');

    if (!$apiKey) {
        return ['error' => 'API key not configured'];
    }

    $payload = [
        "contents" => [
            [
                "parts" => [
                    [
                        "inline_data" => [
                            "mime_type" => "application/pdf",
                            "data" => base64_encode($data['file_blob'])
                        ]
                    ],
                    [
                        "text" => "Analyze this resume for the role of {$data['role']} ({$data['specialization']}). Summarize key skills, experience, and suitability for the position."
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if (isset($decoded['candidates'][0]['content'][0]['text'])) {
        return ['ai_analysis' => $decoded['candidates'][0]['content'][0]['text']];
    }

    return ['raw_response' => $decoded];
}

// ----------------------
// Get AI analysis
// ----------------------
$aiResult = analyzeResumeAI($data);

// ----------------------
// Save AI analysis to database
// ----------------------
if (isset($aiResult['ai_analysis'])) {
    $aiText = $aiResult['ai_analysis'];

    // Optional: parse or compute score/priority here
    $aiScore = null;      // numeric score
    $aiPriority = null;   // High/Medium/Low

    $update = $conn->prepare("
        UPDATE hr_applicant
        SET ai_remarks = ?, ai_score = ?, ai_priority = ?
        WHERE applicant_id = ?
    ");
    $update->bind_param("sisi", $aiText, $aiScore, $aiPriority, $applicantId);
    $update->execute();
}

// ----------------------
// Return result as JSON
// ----------------------
echo json_encode($aiResult);
