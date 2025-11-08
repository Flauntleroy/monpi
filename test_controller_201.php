<?php

// Test melalui endpoint monitoring controller
require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\BpjsMonitoringController;

echo "Testing through BpjsMonitoringController...\n";

// Create controller instance
$controller = new BpjsMonitoringController();

// Create request untuk test custom endpoint
$request = new Request();
$request->merge([
    'url' => 'http://monitoringbpjs.test/bpjs-monitoring/test-error-201',
    'method' => 'GET',
    'timeout' => 10
]);

echo "Testing URL through controller: http://monitoringbpjs.test/bpjs-monitoring/test-error-201\n";

// Call testCustomEndpoint method
$response = $controller->testCustomEndpoint($request);

echo "Controller Response: " . $response->getContent() . "\n";
echo "HTTP Status: " . $response->getStatusCode() . "\n";

echo "Check your WhatsApp for notification!\n";
