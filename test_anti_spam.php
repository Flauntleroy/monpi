<?php

// Test anti-spam system
require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Helpers\FonnteWhatsapp;

echo "Testing anti-spam system...\n\n";

// Test 1: Kirim pesan yang sama 3 kali
echo "Test 1: Mengirim pesan yang sama 3 kali dalam waktu singkat\n";
for ($i = 1; $i <= 3; $i++) {
    echo "Attempt $i: ";
    $result = FonnteWhatsapp::sendEndpointAlert("Test Endpoint", "404", "Not Found", "http://test.com", 1); // 1 menit cooldown untuk test
    echo $result['status'] . " - " . ($result['message'] ?? 'Success') . "\n";
    sleep(1); // delay 1 detik
}

echo "\nTest 2: Mengirim pesan berbeda (tidak terkena cooldown)\n";
$result = FonnteWhatsapp::sendEndpointAlert("Different Endpoint", "500", "Internal Error", "http://different.com", 1);
echo "Result: " . $result['status'] . " - " . ($result['message'] ?? 'Success') . "\n";

echo "\nTest 3: Critical Alert dengan cooldown 2 menit\n";
$result = FonnteWhatsapp::sendCriticalAlert("Critical Test", "Database connection failed", "http://critical.com", 2);
echo "Result: " . $result['status'] . " - " . ($result['message'] ?? 'Success') . "\n";

// Test kirim lagi dalam 5 detik
echo "\nTest 4: Kirim critical alert yang sama lagi dalam 5 detik (should be skipped)\n";
sleep(5);
$result = FonnteWhatsapp::sendCriticalAlert("Critical Test", "Database connection failed", "http://critical.com", 2);
echo "Result: " . $result['status'] . " - " . ($result['message'] ?? 'Success') . "\n";

echo "\nDone! Check Laravel logs to see cooldown behavior.\n";
