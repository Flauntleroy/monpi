<?php

// Test endpoint monitoring dengan URL yang memberikan status 201
require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Helpers\FonnteWhatsapp;

echo "Testing monitoring with 201 status...\n";

// Test URL yang memberikan status 201
$testUrl = 'http://monitoringbpjs.test/bpjs-monitoring/test-error-201';

echo "Testing URL: $testUrl\n";

// Simulate monitoring call
$response = Http::get($testUrl);
$statusCode = $response->status();

echo "Response Status: $statusCode\n";
echo "Response Body: " . $response->body() . "\n";

// Check jika status 201 dan kirim notifikasi
if ($statusCode == 201) {
    echo "Status 201 detected! Sending WhatsApp notification...\n";
    
    $message = "API Status 201 Test: url: $testUrl, status: $statusCode";
    $result = FonnteWhatsapp::send($message);
    
    echo "WhatsApp Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No notification sent (status was $statusCode, not 201)\n";
}

echo "Done!\n";
