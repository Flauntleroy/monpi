<?php

require_once 'vendor/autoload.php';

use App\Helpers\FonnteWhatsapp;

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test WhatsApp notification
echo "Testing WhatsApp notification...\n";

$result = FonnteWhatsapp::send("Test notification from BPJS Monitoring System - " . date('Y-m-d H:i:s'));

echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";

echo "Check your WhatsApp and Laravel logs for more details.\n";
