<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\BpjsMonitoringController;
// Removed: BpjsMonitoringControllerSimple (unused)
use App\Http\Controllers\BpjsMonitoringControllerDebug;
use App\Http\Controllers\NetworkDiagnosticController;
use App\Http\Controllers\CustomEndpointController;
use App\Http\Controllers\SensorMonitoringController;

Route::get('/', function () {
    return redirect('/bpjs-monitoring');
})->name('home');

// Removed multiple dev/test routes for production cleanliness

// Commented out static BPJS data - using real-time controller instead
/*
// Simple BPJS monitoring endpoint without dependencies
Route::get('/simple-bpjs-data', function () {
    // Sample data to test the frontend
    return response()->json([
        'summary' => [
            'total' => 10,
            'success' => 8,
            'error' => 2,
            'avg_response_time' => 1250.5,
            'uptime_percentage' => 80.0,
            'uptime_24h' => 85.5,
            'avg_response_time_24h' => 1100.2
        ],
        'endpoints' => [
            [
                'name' => 'Diagnosa',
                'url' => 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/diagnosa/A00',
                'response_time' => 1250.5,
                'code' => '200',
                'message' => 'OK',
                'status' => 'success',
                'severity' => 'slow',
                'description' => 'Referensi data diagnosa'
            ],
            [
                'name' => 'Poli',
                'url' => 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/poli/INT',
                'response_time' => 2500.0,
                'code' => 'ERROR',
                'message' => 'Connection timeout',
                'status' => 'timeout',
                'severity' => 'critical',
                'description' => 'Referensi data poli'
            ],
            [
                'name' => 'Faskes',
                'url' => 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/faskes/0101R001/1',
                'response_time' => 850.2,
                'code' => '200',
                'message' => 'OK',
                'status' => 'success',
                'severity' => 'good',
                'description' => 'Referensi data fasilitas kesehatan'
            ]
        ],
        'alerts' => [
            [
                'id' => 1,
                'endpoint_name' => 'Poli',
                'type' => 'response_time',
                'message' => 'Response time exceeded critical threshold',
                'triggered_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
                'data' => ['threshold' => 2000, 'actual' => 2500]
            ]
        ],
        'statistics' => [
            'hourly_data' => collect(range(0, 23))->map(function ($hour) {
                return [
                    'time' => now()->subHours(23 - $hour)->toISOString(),
                    'avg_response_time' => rand(800, 2000),
                    'uptime_percentage' => rand(75, 100),
                    'total_checks' => 10,
                    'successful_checks' => rand(7, 10)
                ];
            })->toArray(),
            'trends' => [
                'response_time_trend' => 'stable',
                'uptime_trend' => 'improving'
            ]
        ],
        'timestamp' => now()->format('Y-m-d H:i:s')
    ]);
});
*/

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Mini Monitoring (lite) - hanya card monitors
Route::get('/mini-monitoring', function () {
    return Inertia::render('BpjsMonitoring/DashboardLite');
})->name('mini-monitoring');

// BPJS Monitoring Routes (Public Access for Testing)
Route::prefix('bpjs-monitoring')->name('bpjs.')->group(function () {
    // Removed: test-basic and simple HTML testing routes
    
    // Gunakan halaman khusus BPJS Monitoring, terpisah dari dashboard default Laravel
    Route::get('/', function () {
        return Inertia::render('BpjsMonitoring/Dashboard');
    })->name('index');
    Route::get('/data', [BpjsMonitoringControllerDebug::class, 'getMonitoringData'])->name('data');
    
    // Network Diagnostic Dashboard
    Route::get('/network-diagnostic', function () {
        return Inertia::render('BpjsMonitoring/Dashboard');
    })->name('network.diagnostic');
    
    // API untuk Network Diagnostic
    Route::get('/network-diagnostic-data', [NetworkDiagnosticController::class, 'getDiagnosticData'])->name('network.diagnostic.data');
    Route::get('/historical', [BpjsMonitoringController::class, 'getHistoricalData'])->name('historical');
    Route::get('/alerts', [BpjsMonitoringController::class, 'getAlerts'])->name('alerts');
    Route::post('/alerts/{alert}/resolve', [BpjsMonitoringController::class, 'resolveAlert'])->name('alerts.resolve');
    Route::post('/test-custom-endpoint', [BpjsMonitoringControllerDebug::class, 'testCustomEndpoint'])->name('test.custom.endpoint')->withoutMiddleware(['web']);
    
    // CRUD untuk Custom Endpoints (persist di DB)
    Route::get('/custom-endpoints', [CustomEndpointController::class, 'index'])->name('custom.endpoints.index');
    Route::post('/custom-endpoints', [CustomEndpointController::class, 'store'])->name('custom.endpoints.store');
    Route::put('/custom-endpoints/{endpoint}', [CustomEndpointController::class, 'update'])->name('custom.endpoints.update');
    Route::delete('/custom-endpoints/{endpoint}', [CustomEndpointController::class, 'destroy'])->name('custom.endpoints.destroy');
    
    // Removed: artificial error test routes
});

// Sensor Monitoring (DHT22)
Route::get('/sensor-monitoring', function () {
    return Inertia::render('Sensors/SensorMonitoring');
})->name('sensor.monitoring');

// Sensor Monitoring API
Route::get('/sensor-monitoring/data', [SensorMonitoringController::class, 'index']);

// Legacy route for backward compatibility
Route::get('/api/bpjs-monitoring/data', [BpjsMonitoringControllerDebug::class, 'getMonitoringData'])->name('bpjs.legacy.data');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
