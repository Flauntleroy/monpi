<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NetworkDiagnosticController;
use App\Http\Controllers\SensorDhtController;

// Network Diagnostic Routes (prefix 'api' otomatis oleh group API)
Route::get('/network-diagnostic', [NetworkDiagnosticController::class, 'getDiagnosticData']);

// Sensor DHT22 API (NodeMCU)
Route::post('/sensors/dht', [SensorDhtController::class, 'store']);
Route::get('/sensors/dht/recent', [SensorDhtController::class, 'recent']);
Route::post('/sensors/alert', [SensorDhtController::class, 'alert']);

// Debug route untuk testing
Route::post('/sensors/dht/debug', function () {
    return response()->json(['message' => 'Debug endpoint working']);
});
Route::post('/sensors/dht/test', function (Illuminate\Http\Request $request) {
    \Log::info('Debug POST /api/sensors/dht/test', [
        'headers' => $request->headers->all(),
        'body' => $request->all(),
        'raw' => $request->getContent(),
        'method' => $request->method(),
        'content_type' => $request->header('Content-Type'),
    ]);

    return response()->json([
        'message' => 'Debug endpoint',
        'headers' => $request->headers->all(),
        'body' => $request->all(),
        'raw' => $request->getContent(),
        'method' => $request->method(),
        'content_type' => $request->header('Content-Type'),
    ]);
});
