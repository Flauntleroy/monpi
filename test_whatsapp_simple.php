<?php

// Simple test untuk Fonnte API tanpa Laravel
$token = 'nFi7goGNVJiG25gCbL7k';
$phone = '6281256180502';
$message = 'Test notification from BPJS Monitoring - ' . date('Y-m-d H:i:s');

echo "Testing WhatsApp notification...\n";
echo "Phone: $phone\n";
echo "Message: $message\n\n";

// Menggunakan cURL untuk test
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.fonnte.com/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => array(
        'target' => $phone,
        'message' => $message
    ),
    CURLOPT_HTTPHEADER => array(
        'Authorization: ' . $token
    ),
));

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);

curl_close($curl);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "Response: $response\n";

$result = json_decode($response, true);
if ($result) {
    echo "Parsed Response:\n";
    print_r($result);
} else {
    echo "Failed to parse JSON response\n";
}
