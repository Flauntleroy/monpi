<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NetworkDiagnosticController;
use App\Http\Controllers\SensorDhtController;

// Network Diagnostic Routes (prefix 'api' otomatis oleh group API)
Route::get('/network-diagnostic', [NetworkDiagnosticController::class, 'getDiagnosticData']);

// Sensor DHT22 API (NodeMCU)
Route::post('/sensors/dht', [SensorDhtController::class, 'store']);
Route::get('/sensors/dht/recent', [SensorDhtController::class, 'recent']);

// Debug route untuk testing
Route::post('/sensors/dht/debug', function () {
    return response()->json(['message' => 'Debug endpoint working']);
});