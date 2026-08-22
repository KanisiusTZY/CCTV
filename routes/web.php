<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\AdminZoneController;
use App\Http\Controllers\AdminEmployeeController;

// 1. Pintu Masuk Portal Pilih Ruangan Kerja
Route::get('/', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{id}/select', [RoomController::class, 'selectRoom'])->name('rooms.select');
Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
Route::delete('/rooms/{id}', [RoomController::class, 'destroy'])->name('rooms.destroy');

// 2. Route Dashboard Presensi Monitoring Real-Time
Route::get('/dashboard', [PresenceController::class, 'dashboard'])->name('presence.dashboard');
Route::get('/api/live-status', [PresenceController::class, 'getLiveStatus'])->name('presence.live-status');
Route::post('/api/change-source', [PresenceController::class, 'changeSource'])->name('presence.change-source');

// 3. Route Laporan HRD Rekapitulasi Presensi
Route::get('/reports', [PresenceController::class, 'reports'])->name('presence.reports');

// 4. Route Admin Management
Route::prefix('admin')->group(function () {
    // Kelola Zona Meja (Zone Drawer)
    Route::get('/zones', [AdminZoneController::class, 'index'])->name('admin.zones');
    Route::get('/zones/snapshot', [AdminZoneController::class, 'getSnapshot'])->name('admin.zones.snapshot');
    Route::post('/zones/save-all', [AdminZoneController::class, 'saveAllZones'])->name('admin.zones.save-all');
    Route::delete('/zones/{zoneId}', [AdminZoneController::class, 'destroy'])->name('admin.zones.destroy');

    // Kelola Pegawai & Registrasi Wajah
    Route::get('/employees', [AdminEmployeeController::class, 'index'])->name('admin.employees');
    Route::post('/employees', [AdminEmployeeController::class, 'store'])->name('admin.employees.store');
    Route::put('/employees/{id}', [AdminEmployeeController::class, 'update'])->name('admin.employees.update');
    Route::delete('/employees/{id}', [AdminEmployeeController::class, 'destroy'])->name('admin.employees.destroy');
    Route::post('/employees/reload-faces', [AdminEmployeeController::class, 'reloadFaceDb'])->name('admin.employees.reload');
});

// 5. WhatsApp Gateway Webhook & Test API
Route::match(['get', 'post'], '/api/whatsapp/webhook', [App\Http\Controllers\WhatsAppWebhookController::class, 'handle'])->name('whatsapp.webhook');