<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PresenceController;

Route::get('/', function () {
    return redirect()->route('presence.dashboard');
});

// Route Dashboard Presensi Monitoring Real-Time
Route::get('/dashboard', [PresenceController::class, 'dashboard'])->name('presence.dashboard');
Route::get('/api/live-status', [PresenceController::class, 'getLiveStatus'])->name('presence.live-status');
Route::post('/api/change-source', [PresenceController::class, 'changeSource'])->name('presence.change-source');

// Route Laporan HRD Rekapitulasi Presensi
Route::get('/reports', [PresenceController::class, 'reports'])->name('presence.reports');
