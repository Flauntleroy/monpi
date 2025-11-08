<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NetworkDiagnosticController;

// Network Diagnostic Routes
Route::get('/api/network-diagnostic', [NetworkDiagnosticController::class, 'getDiagnosticData']);
