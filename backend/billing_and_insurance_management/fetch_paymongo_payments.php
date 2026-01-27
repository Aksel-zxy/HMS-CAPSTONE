<?php
// Include your database config if you want to sync to DB
include '../../SQL/config.php';  // Adjust path as needed

// PayMongo API details
$apiKey = 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV';  // Your secret key
$baseUrl = 'https://api.paymongo.com/v1/payments';

// Build Basic Auth header
$authHeader = 'Basic ' . base64_encode($apiKey . ':');

// Query parameters: Get paid payments, limit to 100 per request
$queryParams = http_build_query([
    'status' => 'paid',
    'limit' => 100  // Adjust as needed; use pagination for more
]);

// Full URL
$url = $baseUrl . '?' . $queryParams;

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $authHeader,
    'Content-Type: application/json'
]);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Handle response
if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['data'])) {
        echo "Fetched " . count($data['data']) . " paid payments:\n";
        foreach ($data['data'] as $payment) {
            $attributes = $payment['attributes'];
            $paymentId = $payment['id'];  // PayMongo payment ID
            $amount = $attributes['amount'] / 100;  // Amount in PHP (converted from centavos)
            $status = $attributes['status'];
            $reference = $attributes['reference_number'] ?? null;  // Your transaction reference
            $remarks = $attributes['remarks'] ?? null;
            $createdAt = $attributes['created_at'];

            // Print details (for debugging)
            echo "Payment ID: $paymentId | Amount: $amount PHP | Status: $status | Reference: $reference | Remarks: $remarks | Created: $createdAt\n";

            // Optional: Sync to your database if reference matches
            if ($reference) {
                // Check if this transaction_id exists in billing_records
                $stmt = $conn->prepare("SELECT billing_id FROM billing_records WHERE transaction_id = ? LIMIT 1");
                $stmt->bind_param("s", $reference);
                $stmt->execute();
                $record = $stmt->get_result()->fetch_assoc();

                if ($record) {
                    // Update status to Paid if not already
                    $updateStmt = $conn->prepare("UPDATE billing_records SET status = 'Paid', paymongo_reference = ? WHERE transaction_id = ?");
                    $updateStmt->bind_param("ss", $paymentId, $reference);
                    $updateStmt->execute();
                    echo "Updated billing_records for transaction_id: $reference\n";

                    // Similarly for patient_receipt
                    $updateStmt2 = $conn->prepare("UPDATE patient_receipt SET status = 'Paid', paymongo_reference = ? WHERE transaction_id = ?");
                    $updateStmt2->bind_param("ss", $paymentId, $reference);
                    $updateStmt2->execute();
                    echo "Updated patient_receipt for transaction_id: $reference\n";
                } else {
                    echo "No matching record in database for reference: $reference\n";
                }
            }
        }

        // Handle pagination if there are more results
        if (isset($data['links']['next'])) {
            echo "Next page: " . $data['links']['next'] . "\n";  // You can loop to fetch more
        }
    } else {
        echo "No payments found or invalid response.\n";
    }
} else {
    echo "API Error: HTTP $httpCode | Response: $response\n";
}
?>