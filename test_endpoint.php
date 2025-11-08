<?php

// Simple test to check if our endpoint works
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\BpjsMonitoringControllerSimple;

// Create a mock request
$request = Request::create('/bpjs-monitoring/data', 'GET');

try {
    // Create controller instance
    $controller = new BpjsMonitoringControllerSimple();
    
    // Call the method
    $response = $controller->getMonitoringData();
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Content: " . $response->getContent() . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
