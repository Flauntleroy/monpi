<?php

use App\Http\Controllers\NetworkDiagnosticController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Network Diagnostic Dashboard
Route::get('/network-diagnostic', function () {
    return Inertia::render('NetworkDiagnostic');
});
