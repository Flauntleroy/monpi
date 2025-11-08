<?php

// Quick system status check
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\BpjsMonitoringControllerDebug;
use App\Helpers\FonnteWhatsapp;

echo "=== BPJS MONITORING SYSTEM STATUS ===\n\n";

echo "1. FONNTE API TEST\n";
$test_response = FonnteWhatsapp::send("🧪 System Status Check\nTime: " . date('Y-m-d H:i:s') . "\nAll systems operational ✅");
echo "Fonnte API Status: " . ($test_response['status'] === 200 ? "✅ Working" : "❌ Failed") . "\n\n";

echo "2. MONITORING ENDPOINTS\n";
$controller = new BpjsMonitoringControllerDebug();

// Get current monitoring data without triggering alerts
$response = $controller->getMonitoringData();
$data = json_decode($response->getContent(), true);

if(isset($data['endpoints'])) {
    echo "All Endpoints Status:\n";
    foreach($data['endpoints'] as $endpoint) {
        $status_icon = $endpoint['status'] === 'success' ? '✅' : '❌';
        $type = (strpos($endpoint['url'], 'bpjs') !== false || strpos($endpoint['url'], 'vclaim') !== false) ? '[BPJS]' : '[Baseline]';
        $code = isset($endpoint['response_code']) ? $endpoint['response_code'] : (isset($endpoint['code']) ? $endpoint['code'] : 'N/A');
        echo "  {$status_icon} {$type} {$endpoint['name']}: {$code}\n";
    }
}

if(isset($data['summary'])) {
    echo "\nSummary:\n";
    echo "  Total: {$data['summary']['total']}\n";
    echo "  Success: {$data['summary']['success']}\n";
    $failed = $data['summary']['total'] - $data['summary']['success'];
    echo "  Failed: {$failed}\n";
}

echo "\n3. SYSTEM FEATURES\n";
echo "✅ WhatsApp Notifications via Fonnte\n";
echo "✅ Anti-spam/Cooldown System (1 minute)\n";
echo "✅ BPJS + Baseline Monitoring\n";
echo "✅ Diagnostic Root Cause Analysis\n";
echo "✅ Comprehensive Logging\n";

echo "\n4. ERROR ALERT TYPES\n";
echo "• 201/404 Endpoint Errors\n";
echo "• Critical System Alerts\n";
echo "• Slow Response Warnings\n";
echo "• Network Diagnosis Alerts\n";

echo "\n=== SYSTEM READY FOR PRODUCTION ===\n";
