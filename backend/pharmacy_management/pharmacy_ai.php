<?php
function analyzeStockAI($data)
{
    // ✅ Use your API key directly
    $apiKey = "AIzaSyB4PaAtLoKhVVYe9TleiFGweps0JdXChvQ";

    if (!$apiKey) {
        return "AI analysis unavailable: No API key set.";
    }

    $prompt = "
You are a hospital pharmacy inventory assistant.

Medicine Name: {$data['med_name']}
Dosage: {$data['dosage']}
Current Stock: {$data['stock']}
Reorder Level: {$data['reorder']}
Average Daily Usage: {$data['avg_use']}
Stock Status: {$data['status']}
Movement: {$data['movement']}

Answer:
1. Should stock be requested?
2. Urgency level (Low, Medium, High)
3. Short reason

Explain based on stock, average daily usage, and movement. Use different reasons if stock or movement differs.
";

    $payload = [
        "contents" => [[
            "parts" => [["text" => $prompt]]
        ]]
    ];

    // Use text-bison-001 model for free-tier compatibility
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=$apiKey";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return "AI analysis unavailable: cURL error.";
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    return "AI analysis unavailable: No response from API.";
}
