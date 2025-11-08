<?php

/**
 * Test specific BPJS endpoint with correct credentials
 */

// Updated BPJS API Configuration with correct credentials
$consid = "21095"; // Consumer ID
$secretkey = "468C5DFE8E9"; // Secret Key 
$userkey = "bdc202481c6658e7c8b25ac7ee65c7dc"; // User Key
$base_url = "https://apijkn.bpjs-kesehatan.go.id";

// Test the specific endpoint mentioned by user
$test_endpoints = [
    "/vclaim-rest/referensi/kelasrawat",
    "/vclaim-rest/referensi/spesialistik", 
    "/vclaim-rest/Peserta/nik/6304151101990001/tglSEP/2025-07-31"
];

function generateBpjsHeaders($consid, $secretkey, $userkey) {
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
    
    // Create signature exactly like in Laravel controller
    $signature = base64_encode(hash_hmac('sha256', $consid . '&' . $tStamp, $secretkey, true));

    return [
        'X-cons-id: ' . $consid,
        'X-timestamp: ' . $tStamp,
        'X-signature: ' . $signature,
        'user_key: ' . $userkey,
        'Content-Type: application/json'
    ];
}

echo "🧪 Testing BPJS Endpoints with Updated Credentials\n";
echo "===============================================\n";
echo "Consumer ID: " . $consid . "\n\n";

foreach ($test_endpoints as $test_url) {
    $full_url = $base_url . $test_url;
    $headers = generateBpjsHeaders($consid, $secretkey, $userkey);
    
    echo "Testing: " . $test_url . "\n";
    echo "URL: " . $full_url . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $response_time = round(($end_time - $start_time) * 1000, 2);
    
    echo "📊 HTTP Code: " . $http_code . " | Response Time: " . $response_time . "ms\n";
    
    if ($error) {
        echo "❌ cURL Error: " . $error . "\n";
    }
    
    if ($response) {
        $json = json_decode($response, true);
        if ($json && isset($json['metaData'])) {
            $code = $json['metaData']['code'] ?? 'N/A';
            $message = $json['metaData']['message'] ?? 'N/A';
            echo "BPJS Code: " . $code . " | Message: " . $message . "\n";
            
            if ($code == '200') {
                echo "✅ SUCCESS!\n";
            } else {
                echo "⚠️ BPJS Error: " . $message . "\n";
            }
        }
    } else {
        echo "❌ No response received\n";
    }
    
    echo str_repeat("-", 60) . "\n\n";
}

?>
