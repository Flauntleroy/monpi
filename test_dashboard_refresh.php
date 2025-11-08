<?php

// Test monitoring data endpoint yang digunakan saat dashboard refresh
require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\BpjsMonitoringControllerDebug;

echo "Testing BpjsMonitoringControllerDebug->getMonitoringData()...\n";
echo "This simulates what happens when dashboard is refreshed.\n\n";

// Create controller instance
$controller = new BpjsMonitoringControllerDebug();

// Call getMonitoringData method (yang dipanggil saat dashboard refresh)
$response = $controller->getMonitoringData();

echo "Controller Response Status: " . $response->getStatusCode() . "\n";

$data = json_decode($response->getContent(), true);

echo "Summary:\n";
echo "- Total: " . $data['summary']['total'] . "\n";
echo "- Success: " . $data['summary']['success'] . "\n";
echo "- Error: " . $data['summary']['error'] . "\n";
echo "- Avg Response Time: " . $data['summary']['avg_response_time'] . "ms\n\n";

echo "Endpoints:\n";
foreach ($data['endpoints'] as $endpoint) {
    echo "- {$endpoint['name']}: {$endpoint['status']} (Code: {$endpoint['code']}, Time: {$endpoint['response_time']}ms)\n";
}

echo "\nCheck your WhatsApp for any notifications!\n";
echo "If there are any errors (404, timeouts, etc.), you should receive WhatsApp notifications.\n";
