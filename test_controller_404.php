<?php

// Test status 404
require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\BpjsMonitoringController;

echo "Testing 404 status through controller...\n";

// Create controller instance
$controller = new BpjsMonitoringController();

// Create request untuk test endpoint yang tidak ada (404)
$request = new Request();
$request->merge([
    'url' => 'http://monitoringbpjs.test/non-existent-endpoint-404',
    'method' => 'GET',
    'timeout' => 10
]);

echo "Testing URL: http://monitoringbpjs.test/non-existent-endpoint-404\n";

// Call testCustomEndpoint method
$response = $controller->testCustomEndpoint($request);

echo "Controller Response: " . $response->getContent() . "\n";
echo "HTTP Status: " . $response->getStatusCode() . "\n";

echo "Check your WhatsApp for 404 notification!\n";
