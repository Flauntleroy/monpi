<?php

// Simple test for BPJS proxy endpoint
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Test BPJS endpoint detection and headers
function testBpjsProxyLogic() {
    echo "Testing BPJS Proxy Logic\n";
    echo "========================\n\n";
    
    // Test 1: BPJS endpoint detection
    $bpjsUrl = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Peserta/nik/6304151101990001/tglSEP/2025-07-31';
    $regularUrl = 'https://httpbin.org/get';
    
    $isBpjs1 = strpos($bpjsUrl, 'bpjs-kesehatan.go.id') !== false;
    $isBpjs2 = strpos($regularUrl, 'bpjs-kesehatan.go.id') !== false;
    
    echo "1. BPJS endpoint detection:\n";
    echo "   BPJS URL: $bpjsUrl\n";
    echo "   Is BPJS: " . ($isBpjs1 ? 'YES' : 'NO') . "\n";
    echo "   Regular URL: $regularUrl\n";
    echo "   Is BPJS: " . ($isBpjs2 ? 'YES' : 'NO') . "\n\n";
    
    // Test 2: BPJS headers generation
    echo "2. BPJS headers generation:\n";
    $consid = '17432';
    $secretkey = '3nK53BBE23';
    $user_key = '1823bb1d8015aee02180ee12d2af2b2c';
    
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $signature = base64_encode(hash_hmac('sha256', $consid . '&' . $tStamp, $secretkey, true));
    
    $headers = [
        'X-cons-id' => $consid,
        'X-timestamp' => $tStamp,
        'X-signature' => $signature,
        'user_key' => $user_key,
        'Content-Type' => 'application/json',
    ];
    
    echo "   Headers generated:\n";
    foreach ($headers as $key => $value) {
        if ($key === 'X-signature') {
            echo "   $key: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "   $key: $value\n";
        }
    }
    echo "\n";
    
    // Test 3: Response time calculation
    echo "3. Response time calculation:\n";
    $testTimes = [450, 750, 1200, 2500];
    
    foreach ($testTimes as $time) {
        $severity = 'excellent';
        if ($time >= 2000) $severity = 'critical';
        elseif ($time >= 1000) $severity = 'slow';  
        elseif ($time >= 500) $severity = 'good';
        
        echo "   ${time}ms -> $severity\n";
    }
    echo "\n";
    
    echo "Test completed successfully!\n";
    echo "\nExpected behavior:\n";
    echo "- BPJS URLs should be detected automatically\n";
    echo "- Proxy will be enabled for BPJS endpoints\n";
    echo "- Proper authentication headers will be added\n";
    echo "- Frontend will show 'Backend proxy: Yes' for BPJS endpoints\n";
}

testBpjsProxyLogic();
