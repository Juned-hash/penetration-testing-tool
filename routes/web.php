<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Guest Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// Protected Application Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/scans', [ScanController::class, 'index'])->name('scans.index');
    Route::get('/scans/create', [ScanController::class, 'create'])->name('scans.create');
    Route::post('/scans', [ScanController::class, 'store'])->name('scans.store');
    Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('scans.show');
    Route::get('/scans/{scan}/status', [ScanController::class, 'status'])->name('scans.status');
    Route::post('/scans/{scan}/confirm-authorization', [ScanController::class, 'confirmAuthorization'])->name('scans.confirm-authorization');
    Route::post('/scans/{scan}/start', [ScanController::class, 'start'])->name('scans.start');
    Route::post('/scans/{scan}/cancel', [ScanController::class, 'cancel'])->name('scans.cancel');

    // Finding Routes
    Route::get('/scans/{scan}/findings', [\App\Http\Controllers\FindingController::class, 'index'])->name('scans.findings.index');
    Route::get('/scans/{scan}/findings/{finding}', [\App\Http\Controllers\FindingController::class, 'show'])->name('scans.findings.show');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/scans/{scan}/reports/pdf', [ReportController::class, 'generatePdf'])->name('scans.reports.pdf');
    Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
});
