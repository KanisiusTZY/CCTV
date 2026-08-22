<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WorkstationZone;
use App\Models\PresenceEventLog;
use App\Models\DailyZoneSummary;

class PresenceController extends Controller
{
    /**
     * URL Python Stream Server Engine
     */
    private string $pythonApiUrl = 'http://localhost:5000';

    /**
     * Memastikan Python AI Stream Server berjalan di background (port 5000)
     */
    private function ensurePythonEngineRunning()
    {
        try {
            // Cek apakah port 5000 sudah merespon
            $response = Http::timeout(1)->get($this->pythonApiUrl . '/api/status');
            if ($response->successful()) {
                return; // Server sudah aktif
            }
        } catch (\Exception $e) {
            // Python Engine belum aktif -> Jalankan secara otomatis di background
            $scriptPath = base_path('monitor/stream_server.py');
            if (!file_exists($scriptPath)) {
                $scriptPath = 'd:/monitor/stream_server.py';
            }

            if (file_exists($scriptPath)) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    pclose(popen("start /B python \"{$scriptPath}\" --port 5000", "r"));
                } else {
                    exec("python3 \"{$scriptPath}\" --port 5000 > /dev/null 2>&1 &");
                }
                sleep(1); // Jeda 1 detik agar engine sempat inisialisasi
            }
        }
    }

    /**
     * Menampilkan Dashboard Utama Monitoring Kehadiran Live
     */
    public function dashboard()
    {
        // 1. Cek & otomatis jalankan Python Engine jika belum aktif
        $this->ensurePythonEngineRunning();

        // 2. Ambil data status real-time dari Python REST API
        $streamStatus = [];
        try {
            $response = Http::timeout(3)->get($this->pythonApiUrl . '/api/status');
            if ($response->successful()) {
                $streamStatus = $response->json();
                
                // Sinkronkan koordinat boks meja dari Python ke DB secara otomatis (UPSERT)
                if (!empty($streamStatus['zones'])) {
                    $configZones = [];
                    foreach ($streamStatus['zones'] as $zId => $zData) {
                        if (isset($zData['chair_bbox'])) {
                            $configZones[] = ['id' => $zId, 'bbox' => $zData['chair_bbox']];
                        }
                    }
                    WorkstationZone::syncFromConfig($configZones);
                }
            }
        } catch (\Exception $e) {
            $streamStatus = [
                'error' => 'Sedang menghubungkan ke Python AI Stream Server pada ' . $this->pythonApiUrl,
                'total_bekerja' => 0,
                'total_away' => 0,
                'fps' => 0.0,
                'zones' => []
            ];
        }

        // 3. Ambil log event & summary dari database jika DB sudah di-migrate
        $recentLogs = collect();
        $todaySummaries = collect();
        try {
            $recentLogs = PresenceEventLog::with('zone')
                ->orderBy('timestamp', 'desc')
                ->limit(10)
                ->get();

            $todaySummaries = DailyZoneSummary::with('zone')
                ->where('date', date('Y-m-d'))
                ->get();
        } catch (\Exception $e) {
            // Database belum dibuat / belum di-migrate, abaikan error agar dashboard tetap tampil
        }

        return view('presence.dashboard', compact('streamStatus', 'recentLogs', 'todaySummaries'));
    }

    /**
     * API Proxy untuk memperbarui data status real-time via AJAX di Blade & Auto-Sync ke DB
     */
    public function getLiveStatus()
    {
        try {
            $response = Http::timeout(2)->get($this->pythonApiUrl . '/api/status');
            $data = $response->json();

            // Auto-sync ke DB (setiap 3 detik sekali agar super kencang & tanpa error SQL)
            if (!empty($data['zones'])) {
                $lastSync = cache('last_db_presence_sync', 0);
                if (time() - $lastSync >= 3) {
                    cache(['last_db_presence_sync' => time()], 5);
                    $today = date('Y-m-d');
                    $now = date('Y-m-d H:i:s');
                    foreach ($data['zones'] as $zoneId => $zData) {
                        $occSec = $zData['occupied_duration'] ?? 0;
                        $awaySec = $zData['away_duration_seconds'] ?? ($zData['empty_duration'] ?? 0);

                        DailyZoneSummary::updateOrCreate(
                            ['zone_id' => $zoneId, 'date' => $today],
                            [
                                'total_working_seconds' => intval($occSec),
                                'total_away_seconds' => intval($awaySec),
                                'last_updated' => $now
                            ]
                        );
                    }
                }
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'offline', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mengganti sumber video (File MP4 / RTSP CCTV Stream)
     */
    public function changeSource(Request $request)
    {
        $request->validate([
            'source' => 'required|string',
        ]);

        try {
            $response = Http::post($this->pythonApiUrl . '/api/set_source?source=' . urlencode($request->source));
            return redirect()->back()->with('success', 'Sumber video berhasil diubah menjadi: ' . $request->source);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghubungi AI Engine: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan Halaman Laporan Rekapitulasi Presensi HRD
     */
    public function reports(Request $request)
    {
        $selectedDate = $request->get('date', date('Y-m-d'));

        // Sync data real-time dari Python AI Engine sebelum merender halaman laporan
        try {
            $pyRes = Http::timeout(2)->get($this->pythonApiUrl . '/api/status');
            if ($pyRes->successful()) {
                $pyData = $pyRes->json();
                if (!empty($pyData['zones'])) {
                    $today = date('Y-m-d');
                    $now = date('Y-m-d H:i:s');
                    foreach ($pyData['zones'] as $zId => $zData) {
                        $occSec = $zData['occupied_duration'] ?? 0;
                        $awaySec = $zData['away_duration_seconds'] ?? ($zData['empty_duration'] ?? 0);

                        DailyZoneSummary::updateOrCreate(
                            ['zone_id' => $zId, 'date' => $today],
                            [
                                'total_working_seconds' => intval($occSec),
                                'total_away_seconds' => intval($awaySec),
                                'last_updated' => $now
                            ]
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            // Abaikan error jika Python offline
        }

        $summaries = collect();
        try {
            $summaries = DailyZoneSummary::with('zone')
                ->where('date', $selectedDate)
                ->get();
        } catch (\Exception $e) {
            // Database belum di-migrate
        }

        return view('presence.reports', compact('summaries', 'selectedDate'));
    }
}
