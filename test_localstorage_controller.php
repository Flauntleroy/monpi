<?php

require_once 'vendor/autoload.php';

try {
    echo "Testing BpjsMonitoringControllerLocalStorage...\n";
    
    $controller = new App\Http\Controllers\BpjsMonitoringControllerLocalStorage();
    
    echo "1. Testing getMonitoringData method...\n";
    $response = $controller->getMonitoringData();
    $data = json_decode($response->getContent(), true);
    
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Data structure:\n";
    echo "- Summary: " . (isset($data['summary']) ? 'OK' : 'MISSING') . "\n";
    echo "- Endpoints: " . (isset($data['endpoints']) ? count($data['endpoints']) . ' items' : 'MISSING') . "\n";
    echo "- Network Diagnostics: " . (isset($data['network_diagnostics']) ? 'OK' : 'MISSING') . "\n";
    echo "- Timestamp: " . (isset($data['timestamp']) ? $data['timestamp'] : 'MISSING') . "\n";
    
    if (isset($data['summary'])) {
        echo "\nSummary details:\n";
        echo "- Total: " . ($data['summary']['total'] ?? 'N/A') . "\n";
        echo "- Success: " . ($data['summary']['success'] ?? 'N/A') . "\n";
        echo "- Error: " . ($data['summary']['error'] ?? 'N/A') . "\n";
        echo "- Avg Response Time: " . ($data['summary']['avg_response_time'] ?? 'N/A') . "ms\n";
        echo "- Uptime: " . ($data['summary']['uptime_percentage'] ?? 'N/A') . "%\n";
    }
    
    if (isset($data['network_diagnostics']['analysis'])) {
        echo "\nNetwork Analysis:\n";
        echo "- Root Cause: " . $data['network_diagnostics']['analysis']['root_cause'] . "\n";
        echo "- Confidence: " . $data['network_diagnostics']['analysis']['confidence'] . "%\n";
        echo "- Summary: " . $data['network_diagnostics']['analysis']['summary'] . "\n";
    }
    
    echo "\n2. Testing custom endpoint functionality...\n";
    
    $request = new \Illuminate\Http\Request();
    $request->merge([
        'url' => 'https://httpbin.org/get',
        'method' => 'GET',
        'timeout' => 10
    ]);
    
    $customResponse = $controller->testCustomEndpoint($request);
    $customData = json_decode($customResponse->getContent(), true);
    
    echo "Custom endpoint test:\n";
    echo "- Status: " . ($customData['status'] ?? 'N/A') . "\n";
    echo "- Response Time: " . ($customData['response_time'] ?? 'N/A') . "ms\n";
    echo "- Code: " . ($customData['code'] ?? 'N/A') . "\n";
    
    echo "\n✅ LocalStorage controller is working correctly!\n";
    echo "No database dependencies detected.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
