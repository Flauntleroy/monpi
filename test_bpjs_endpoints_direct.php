<?php

require_once 'vendor/autoload.php';

// BPJS credentials
$consid = '17432';
$secretkey = '3nK53BBE23';
$user_key = '1823bb1d8015aee02180ee12d2af2b2c';
$api_url = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/';

function getBpjsHeaders($consid, $secretkey, $user_key) {
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $signature = base64_encode(hash_hmac('sha256', $consid . '&' . $tStamp, $secretkey, true));
    
    return [
        'X-cons-id' => $consid,
        'X-timestamp' => $tStamp,
        'X-signature' => $signature,
        'user_key' => $user_key,
        'Content-Type' => 'application/json',
    ];
}

function testBpjsEndpoint($name, $url, $consid, $secretkey, $user_key) {
    echo "Testing: $name\n";
    echo "URL: $url\n";
    
    $start = microtime(true);
    
    try {
        $headers = getBpjsHeaders($consid, $secretkey, $user_key);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 
                    "X-cons-id: " . $headers['X-cons-id'] . "\r\n" .
                    "X-timestamp: " . $headers['X-timestamp'] . "\r\n" .
                    "X-signature: " . $headers['X-signature'] . "\r\n" .
                    "user_key: " . $headers['user_key'] . "\r\n" .
                    "Content-Type: application/json\r\n",
                'timeout' => 15
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        $end = microtime(true);
        $responseTime = round(($end - $start) * 1000);
        
        if ($response !== false) {
            $json = json_decode($response, true);
            $code = $json['metaData']['code'] ?? 'UNKNOWN';
            $message = $json['metaData']['message'] ?? 'Unknown response';
            
            echo "✅ SUCCESS - Code: $code, Message: $message, Time: {$responseTime}ms\n";
            
            // Show some response data if available
            if (isset($json['response']) && is_array($json['response'])) {
                $responseCount = count($json['response']);
                echo "   Response data: $responseCount items\n";
            }
            
        } else {
            echo "❌ FAILED - No response received, Time: {$responseTime}ms\n";
        }
        
    } catch (Exception $e) {
        $end = microtime(true);
        $responseTime = round(($end - $start) * 1000);
        echo "❌ ERROR - " . $e->getMessage() . ", Time: {$responseTime}ms\n";
    }
    
    echo "\n";
}

echo "=== BPJS Endpoint Testing ===\n\n";

// Test endpoints that should work
$goodEndpoints = [
    ['Timestamp', $api_url . 'timestamp'],
    ['Diagnosa A00', $api_url . 'referensi/diagnosa/A00'],
    ['Poli INT', $api_url . 'referensi/poli/INT'],
    ['Faskes', $api_url . 'referensi/faskes/1702R002/2'],
    ['Propinsi', $api_url . 'referensi/propinsi'],
    ['Kabupaten', $api_url . 'referensi/kabupaten/propinsi/01'],
    ['Kelas Rawat', $api_url . 'referensi/kelasrawat'],
    ['Cara Keluar', $api_url . 'referensi/carakeluar'],
    ['Pasca Pulang', $api_url . 'referensi/pascapulang'],
];

foreach ($goodEndpoints as $endpoint) {
    testBpjsEndpoint($endpoint[0], $endpoint[1], $consid, $secretkey, $user_key);
}

echo "=== Testing Potentially Problematic Endpoints ===\n\n";

$problematicEndpoints = [
    ['Dokter DPJP Complex', $api_url . 'referensi/dokter/pelayanan/1/tglPelayanan/' . date('Y-m-d') . '/Spesialis/INT'],
    ['Procedure 001', $api_url . 'referensi/procedure/001'],
    ['Dokter 266154', $api_url . 'referensi/dokter/266154'],
    ['Spesialistik', $api_url . 'referensi/spesialistik'],
    ['Ruang Rawat', $api_url . 'referensi/ruangrawat'],
    ['Rujukan NoRujukan', $api_url . 'Rujukan/170205010525Y000103'],
    ['Rujukan NoKartu', $api_url . 'Rujukan/Peserta/0002657364478'],
    ['Rujukan TglRujukan', $api_url . 'Rujukan/List/TglRujukan/' . date('Y-m-d')],
];

foreach ($problematicEndpoints as $endpoint) {
    testBpjsEndpoint($endpoint[0], $endpoint[1], $consid, $secretkey, $user_key);
}

echo "=== Test Completed ===\n";
echo "Check results above to identify which endpoints are returning 404\n";
echo "404 errors usually mean:\n";
echo "1. Wrong endpoint URL structure\n";
echo "2. Invalid parameters (dates, codes, IDs)\n";
echo "3. Endpoint deprecated or moved\n";
echo "4. Authentication issues\n\n";
