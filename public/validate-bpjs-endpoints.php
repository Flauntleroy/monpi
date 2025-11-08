<?php

/**
 * Direct test of BPJS endpoints to verify which ones work
 * This helps identify and resolve 404 issues
 */

// BPJS API Configuration
$consid = "21095"; // Consumer ID
$secretkey = "468C5DFE8E9"; // Secret Key
$userkey = "bdc202481c6658e7c8b25ac7ee65c7dc"; // User Key
$base_url = "https://apijkn.bpjs-kesehatan.go.id";

// Test endpoints (removed timestamp - confirmed 404)
$test_endpoints = [
    [
        'name' => 'Faskes List (Page 1)',
        'url' => '/vclaim-rest/referensi/faskes/1/1',
        'description' => 'Healthcare facilities reference'
    ],
    [
        'name' => 'Diagnosa ICD10 - A00',
        'url' => '/vclaim-rest/referensi/diagnosa/A00',
        'description' => 'Diagnosis reference for code A00'
    ],
    [
        'name' => 'Procedure - 001',
        'url' => '/vclaim-rest/referensi/procedure/001',
        'description' => 'Medical procedure reference for code 001'
    ],
    [
        'name' => 'Dokter DPJP',
        'url' => '/vclaim-rest/referensi/dokter/pelayanan/1/tglPelayanan/' . date('Y-m-d') . '/Spesialis/INT',
        'description' => 'Doctor reference for today'
    ],
    [
        'name' => 'Kelas Rawat',
        'url' => '/vclaim-rest/referensi/kelasrawat',
        'description' => 'Treatment class reference'
    ],
    [
        'name' => 'Spesialistik',
        'url' => '/vclaim-rest/referensi/spesialistik',
        'description' => 'Specialist reference'
    ],
    // Test with sample data
    [
        'name' => 'Peserta by NIK (Sample)',
        'url' => '/vclaim-rest/Peserta/nik/1234567890123456/tglSEP/' . date('Y-m-d'),
        'description' => 'Patient data by NIK'
    ]
];

function generateBpjsHeaders($consid, $secretkey, $userkey, $method = 'GET', $url = '') {
    $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
    $signature = hash_hmac('sha256', $consid . "&" . $tStamp, $secretkey, true);
    $encodedSignature = base64_encode($signature);

    return [
        'X-cons-id: ' . $consid,
        'X-timestamp: ' . $tStamp,
        'X-signature: ' . $encodedSignature,
        'user_key: ' . $userkey,
        'Content-Type: application/x-www-form-urlencoded'
    ];
}

function testEndpoint($url, $name, $description) {
    global $consid, $secretkey, $userkey, $base_url;
    
    $full_url = $base_url . $url;
    $headers = generateBpjsHeaders($consid, $secretkey, $userkey, 'GET', $url);
    
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
    
    return [
        'name' => $name,
        'url' => $full_url,
        'description' => $description,
        'http_code' => $http_code,
        'response_time' => $response_time,
        'error' => $error,
        'response' => $response ? substr($response, 0, 200) . '...' : 'No response',
        'success' => $http_code >= 200 && $http_code < 300
    ];
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>🔍 BPJS Endpoint Validator - Fix 404 Issues</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">🔍 BPJS Endpoint Validator</h1>
            <p class="text-gray-600 mb-4">
                Testing BPJS endpoints to identify and resolve 404 issues. 
                <strong>Timestamp endpoint removed</strong> - confirmed returning 404.
            </p>
            <div class="bg-blue-50 border border-blue-200 rounded p-4">
                <p><strong>Testing Time:</strong> <?= date('Y-m-d H:i:s') ?></p>
                <p><strong>Base URL:</strong> <?= $base_url ?></p>
                <p><strong>Total Endpoints:</strong> <?= count($test_endpoints) ?></p>
            </div>
        </div>

        <div class="space-y-4">
            <?php foreach ($test_endpoints as $endpoint): ?>
                <?php $result = testEndpoint($endpoint['url'], $endpoint['name'], $endpoint['description']); ?>
                
                <div class="bg-white rounded-lg shadow p-6 border-l-4 <?= $result['success'] ? 'border-green-500' : 'border-red-500' ?>">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="text-lg font-semibold <?= $result['success'] ? 'text-green-800' : 'text-red-800' ?>">
                                <?= $result['success'] ? '✅' : '❌' ?> <?= htmlspecialchars($result['name']) ?>
                            </h3>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($result['description']) ?></p>
                        </div>
                        <div class="text-right">
                            <span class="px-3 py-1 rounded-full text-sm font-medium <?= $result['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                HTTP <?= $result['http_code'] ?>
                            </span>
                            <p class="text-xs text-gray-500 mt-1"><?= $result['response_time'] ?>ms</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded p-3 mb-3">
                        <p class="text-xs text-gray-600 font-mono break-all"><?= htmlspecialchars($result['url']) ?></p>
                    </div>
                    
                    <?php if ($result['error']): ?>
                        <div class="bg-red-50 border border-red-200 rounded p-3 mb-3">
                            <p class="text-red-700 text-sm"><strong>cURL Error:</strong> <?= htmlspecialchars($result['error']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($result['response'] && $result['success']): ?>
                        <div class="bg-green-50 border border-green-200 rounded p-3">
                            <p class="text-green-700 text-sm"><strong>Response Preview:</strong></p>
                            <pre class="text-xs text-gray-600 mt-1 whitespace-pre-wrap"><?= htmlspecialchars($result['response']) ?></pre>
                        </div>
                    <?php elseif ($result['response']): ?>
                        <div class="bg-red-50 border border-red-200 rounded p-3">
                            <p class="text-red-700 text-sm"><strong>Error Response:</strong></p>
                            <pre class="text-xs text-gray-600 mt-1 whitespace-pre-wrap"><?= htmlspecialchars($result['response']) ?></pre>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action buttons for working endpoints -->
                    <?php if ($result['success']): ?>
                        <div class="mt-3 pt-3 border-t border-gray-200">
                            <button onclick="addToCustomEndpoints('<?= addslashes($result['name']) ?>', '<?= addslashes($result['url']) ?>', '<?= addslashes($result['description']) ?>')" 
                                    class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                                Add to Custom Endpoints
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Summary -->
        <?php 
        $total = count($test_endpoints);
        $success_count = 0;
        foreach ($test_endpoints as $endpoint) {
            $result = testEndpoint($endpoint['url'], $endpoint['name'], $endpoint['description']);
            if ($result['success']) $success_count++;
        }
        $failure_count = $total - $success_count;
        ?>
        
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">📊 Test Summary</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 border border-blue-200 rounded p-4 text-center">
                    <div class="text-2xl font-bold text-blue-800"><?= $total ?></div>
                    <div class="text-blue-600">Total Tested</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded p-4 text-center">
                    <div class="text-2xl font-bold text-green-800"><?= $success_count ?></div>
                    <div class="text-green-600">Success</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded p-4 text-center">
                    <div class="text-2xl font-bold text-red-800"><?= $failure_count ?></div>
                    <div class="text-red-600">Failed</div>
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <h3 class="font-semibold text-yellow-800 mb-2">🔧 Next Steps:</h3>
                <ol class="list-decimal list-inside text-yellow-700 space-y-1">
                    <li>Add working endpoints to your custom endpoints list</li>
                    <li>Clear problematic endpoints from localStorage using <a href="/fix-404-endpoints.html" class="underline text-blue-600">Fix Tool</a></li>
                    <li>Return to <a href="/bpjs-monitoring" class="underline text-blue-600">BPJS Dashboard</a> to test</li>
                    <li>Monitor for 404 errors and remove any additional problematic endpoints</li>
                </ol>
            </div>
        </div>
        
        <div class="text-center mt-6 space-x-4">
            <a href="/fix-404-endpoints.html" 
               class="inline-block px-6 py-3 bg-yellow-500 text-white rounded hover:bg-yellow-600">
                🔧 Fix localStorage Issues
            </a>
            <a href="/bpjs-monitoring" 
               class="inline-block px-6 py-3 bg-blue-500 text-white rounded hover:bg-blue-600">
                🚀 Go to Dashboard
            </a>
        </div>
    </div>

    <script>
        function addToCustomEndpoints(name, url, description) {
            const stored = localStorage.getItem('bpjs_custom_endpoints');
            let endpoints = [];
            
            if (stored) {
                try {
                    endpoints = JSON.parse(stored);
                } catch (e) {
                    endpoints = [];
                }
            }
            
            // Check if endpoint already exists
            const exists = endpoints.some(ep => ep.url === url);
            if (exists) {
                alert('⚠️ Endpoint already exists in custom endpoints!');
                return;
            }
            
            const newEndpoint = {
                id: Date.now().toString(),
                name: name,
                url: url,
                description: description,
                method: 'GET',
                headers: {},
                timeout: 15,
                isActive: true,
                isBpjsEndpoint: url.includes('bpjs-kesehatan.go.id'),
                useProxy: url.includes('bpjs-kesehatan.go.id')
            };
            
            endpoints.push(newEndpoint);
            localStorage.setItem('bpjs_custom_endpoints', JSON.stringify(endpoints));
            alert(`✅ Added "${name}" to custom endpoints!`);
        }
    </script>
</body>
</html>
