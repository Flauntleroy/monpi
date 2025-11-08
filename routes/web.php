<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\BpjsMonitoringController;
use App\Http\Controllers\BpjsMonitoringControllerSimple;
use App\Http\Controllers\BpjsMonitoringControllerDebug;
use App\Http\Controllers\NetworkDiagnosticController;

Route::get('/', function () {
    return redirect('/bpjs-monitoring');
})->name('home');

// Test route for real-time fix validation
Route::get('/test', function () {
    return redirect('/test-realtime-fix.html');
})->name('test');

// Debug route for API testing
Route::get('/api-test', function () {
    try {
        $controller = new App\Http\Controllers\BpjsMonitoringControllerDebug();
        $response = $controller->getMonitoringData();
        return $response;
    } catch (\Exception $e) {
        return response()->json([
            'error' => true,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// GET route for custom endpoint testing (no CSRF needed)
Route::get('/test-custom-endpoint-get', function (Request $request) {
    try {
        $url = $request->query('url', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Peserta/nik/6304151101990001/tglSEP/2025-07-31');
        $controller = new App\Http\Controllers\BpjsMonitoringControllerDebug();
        $testRequest = new Request();
        $testRequest->merge([
            'url' => $url,
            'method' => 'GET',
            'timeout' => 10
        ]);
        return $controller->testCustomEndpoint($testRequest);
    } catch (\Exception $e) {
        return response()->json([
            'error' => true,
            'message' => $e->getMessage(),
            'response_time' => 0,
            'code' => 'ERROR',
            'status' => 'error',
            'severity' => 'critical'
        ], 500);
    }
});

// Simple test route
Route::get('/test-json', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working',
        'timestamp' => now()->format('Y-m-d H:i:s')
    ]);
});

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

// BPJS Monitoring Routes (Public Access for Testing)
Route::prefix('bpjs-monitoring')->name('bpjs.')->group(function () {
    // Test route to check if basic route works
    Route::get('/test-basic', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'BPJS Monitoring route is working',
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);
    })->name('test.basic');
    
    // Simple HTML version for testing
    Route::get('/simple', function () {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <title>BPJS Monitoring Dashboard</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100 p-8">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-3xl font-bold mb-6">BPJS Monitoring Dashboard</h1>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="mb-4">Dashboard is working! Visit these endpoints:</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li><a href="/bpjs-monitoring/data" class="text-blue-600 hover:underline">Real-time Data API</a></li>
                        <li><a href="/test-realtime-fix.html" class="text-blue-600 hover:underline">Test Interface</a></li>
                        <li><a href="/bpjs-monitoring/test-basic" class="text-blue-600 hover:underline">Basic Route Test</a></li>
                    </ul>
                </div>
            </div>
        </body>
        </html>';
    })->name('simple');
    
    Route::get('/', function () {
        return Inertia::render('Dashboard');
    })->name('index');
    Route::get('/data', [BpjsMonitoringControllerDebug::class, 'getMonitoringData'])->name('data');
    
    // Network Diagnostic Dashboard - route baru
    Route::get('/network-diagnostic', function () {
        return Inertia::render('Dashboard');
    })->name('network.diagnostic');
    
    // API untuk Network Diagnostic
    Route::get('/network-diagnostic-data', [NetworkDiagnosticController::class, 'getDiagnosticData'])->name('network.diagnostic.data');
    Route::get('/historical', [BpjsMonitoringController::class, 'getHistoricalData'])->name('historical');
    Route::get('/alerts', [BpjsMonitoringController::class, 'getAlerts'])->name('alerts');
    Route::post('/alerts/{alert}/resolve', [BpjsMonitoringController::class, 'resolveAlert'])->name('alerts.resolve');
    Route::post('/test-custom-endpoint', [BpjsMonitoringControllerDebug::class, 'testCustomEndpoint'])->name('test.custom.endpoint')->withoutMiddleware(['web']);
    
    // Test route untuk trigger error critical dan notifikasi WhatsApp
    Route::get('/test-error-404', function () {
        return response()->json(['message' => 'Test error 404'], 404);
    })->name('test.error.404');
    
    Route::get('/test-error-201', function () {
        return response()->json(['message' => 'Test error 201'], 201);
    })->name('test.error.201');
});

// Legacy route for backward compatibility
Route::get('/api/bpjs-monitoring/data', [BpjsMonitoringControllerDebug::class, 'getMonitoringData'])->name('bpjs.legacy.data');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
