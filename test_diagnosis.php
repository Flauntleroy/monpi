<?php

// Test dengan endpoint baseline yang gagal untuk simulate masalah internet
require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Helpers\FonnteWhatsapp;

echo "Testing diagnosis scenarios...\n\n";

// Scenario 1: BPJS error + Baseline success = BPJS server issue
echo "Scenario 1: BPJS API issue (baseline working)\n";
$result1 = FonnteWhatsapp::sendDiagnosisAlert('error', 'success', 1);
echo "Result: " . $result1['status'] . "\n\n";

sleep(2);

// Scenario 2: BPJS success + Baseline error = Internet/DNS issue  
echo "Scenario 2: Internet/DNS issue (BPJS working)\n";
$result2 = FonnteWhatsapp::sendDiagnosisAlert('success', 'error', 1);
echo "Result: " . $result2['status'] . "\n\n";

sleep(2);

// Scenario 3: BPJS error + Baseline error = Total internet failure
echo "Scenario 3: Total internet connection issue\n";
$result3 = FonnteWhatsapp::sendDiagnosisAlert('error', 'error', 1);
echo "Result: " . $result3['status'] . "\n\n";

echo "Check WhatsApp for diagnosis messages!\n";
