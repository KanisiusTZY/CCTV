<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WorkstationZone;
use App\Models\PresenceEventLog;
use App\Models\DailyZoneSummary;
use App\Models\Employee;

class PresenceController extends Controller
{
    private string $pythonApiUrl = 'http://localhost:5000';

    private function ensurePythonEngineRunning()
    {
        try {
            $response = Http::timeout(1)->get($this->pythonApiUrl . '/api/status');
            if ($response->successful()) {
                return;
            }
        } catch (\Exception $e) {
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
                sleep(1);
            }
        }
    }

    /**
     * Memperkaya data zona dari Python dengan data pegawai dari database
     */
    private function enrichZonesWithEmployeeData(array $zones)
    {
        try {
            $dbZones = WorkstationZone::with('employee')->get()->keyBy('zone_id');
            foreach ($zones as $zoneId => &$zoneData) {
                $dbZone = $dbZones->get($zoneId);
                if ($dbZone) {
                    $zoneData['zone_name'] = $dbZone->zone_name;
                    if ($dbZone->employee) {
                        $zoneData['employee_name'] = $dbZone->employee->name;
                        $zoneData['employee_position'] = $dbZone->employee->position ?? 'Pegawai';
                        $zoneData['employee_photo'] = $dbZone->employee->photo_filename;
                        $zoneData['display_title'] = $dbZone->employee->name;
                        $zoneData['display_subtitle'] = $dbZone->zone_name . ($dbZone->employee->position ? ' â€¢ ' . $dbZone->employee->position : '');
                    } else {
                        $zoneData['employee_name'] = null;
                        $zoneData['employee_position'] = null;
                        $zoneData['employee_photo'] = null;
                        $zoneData['display_title'] = $dbZone->zone_name;
                        $zoneData['display_subtitle'] = 'Belum Ditugaskan';
                    }
                } else {
                    $zoneData['zone_name'] = 'Meja ' . str_replace('chair_', '', $zoneId);
                    $zoneData['employee_name'] = null;
                    $zoneData['employee_position'] = null;
                    $zoneData['employee_photo'] = null;
                    $zoneData['display_title'] = $zoneData['zone_name'];
                    $zoneData['display_subtitle'] = 'Meja Kerja';
                }
            }
            unset($zoneData);
        } catch (\Exception $e) {}

        return $zones;
    }

    public function dashboard()
    {
        $this->ensurePythonEngineRunning();

        $streamStatus = [];
        try {
            $response = Http::timeout(3)->get($this->pythonApiUrl . '/api/status');
            if ($response->successful()) {
                $streamStatus = $response->json();
                
                if (!empty($streamStatus['zones'])) {
                    $configZones = [];
                    foreach ($streamStatus['zones'] as $zId => $zData) {
                        if (isset($zData['chair_bbox'])) {
                            $configZones[] = ['id' => $zId, 'bbox' => $zData['chair_bbox']];
                        }
                    }
                    WorkstationZone::syncFromConfig($configZones);
                    $streamStatus['zones'] = $this->enrichZonesWithEmployeeData($streamStatus['zones']);
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
        } catch (\Exception $e) {}

        return view('presence.dashboard', compact('streamStatus', 'recentLogs', 'todaySummaries'));
    }

    public function getLiveStatus()
    {
        try {
            $response = Http::timeout(2)->get($this->pythonApiUrl . '/api/status');
            $data = $response->json();

            if (!empty($data['zones'])) {
                // Perkaya dengan data penugasan pegawai dari MySQL
                $data['zones'] = $this->enrichZonesWithEmployeeData($data['zones']);

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

    public function changeSource(Request $request)
    {
        $request->validate([
            'source' => 'required|string',
        ]);

        $newSource = trim($request->source);

        // 1. Simpan ke config.json agar persisten
        $configPath = base_path('monitor/config.json');
        if (file_exists($configPath)) {
            $cfg = json_decode(file_get_contents($configPath), true) ?: [];
            $cfg['source'] = $newSource;
            file_put_contents($configPath, json_encode($cfg, JSON_PRETTY_PRINT));
        }

        // 2. Update data room di database
        if ($activeRoomId = session('active_room_id')) {
            \App\Models\Room::where('id', $activeRoomId)->update(['cctv_source' => $newSource]);
        }
        session(['active_room_source' => $newSource]);

        // 3. Tembak Python Engine untuk Hot-Swapping Video
        try {
            $response = Http::timeout(3)->post($this->pythonApiUrl . '/api/set_source?source=' . urlencode($newSource));
            return redirect()->back()->with('success', "Sumber video berhasil diubah dan diputar: {$newSource}");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghubungi AI Engine: ' . $e->getMessage());
        }
    }

    public function reports(Request $request)
    {
        $selectedDate = $request->get('date', date('Y-m-d'));

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
        } catch (\Exception $e) {}

        $summaries = collect();
        try {
            $summaries = DailyZoneSummary::with(['zone.employee'])
                ->where('date', $selectedDate)
                ->get();
        } catch (\Exception $e) {}

        return view('presence.reports', compact('summaries', 'selectedDate'));
    }
}
