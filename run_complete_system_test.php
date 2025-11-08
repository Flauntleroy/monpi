<?php

// Complete monitoring simulation - all scenarios
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\BpjsMonitoringControllerDebug;

echo "=== COMPLETE MONITORING SYSTEM TEST ===\n";
echo "Simulating real monitoring scenarios...\n\n";

$controller = new BpjsMonitoringControllerDebug();

// Wait for cooldown reset
echo "Waiting 65 seconds for cooldown reset...\n";
for($i = 65; $i > 0; $i--) {
    echo "\rCountdown: {$i} seconds ";
    sleep(1);
}
echo "\n\n";

echo "Starting monitoring test...\n";
$result = $controller->getMonitoringData();

// Display summary
echo "\n=== MONITORING SUMMARY ===\n";
echo "Total Endpoints: " . count($result['bpjs_endpoints']) + count($result['baseline_endpoints']) . "\n";
echo "BPJS Endpoints: " . count($result['bpjs_endpoints']) . "\n";
echo "Baseline Endpoints: " . count($result['baseline_endpoints']) . "\n";
echo "BPJS Errors: " . count(array_filter($result['bpjs_endpoints'], fn($e) => $e['status'] !== 'success')) . "\n";
echo "Baseline Errors: " . count(array_filter($result['baseline_endpoints'], fn($e) => $e['status'] !== 'success')) . "\n";

echo "\n=== CHECK YOUR WHATSAPP ===\n";
echo "You should receive:\n";
echo "1. Individual alerts for each failed endpoint\n";
echo "2. A diagnosis alert explaining the root cause\n";
echo "3. No duplicate alerts due to cooldown system\n\n";

echo "=== LOG VERIFICATION ===\n";
echo "Check storage/logs/laravel.log for detailed logging\n";
