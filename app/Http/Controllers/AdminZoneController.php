<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WorkstationZone;
use App\Models\Employee;

class AdminZoneController extends Controller
{
    private string $pythonApiUrl = 'http://localhost:5000';

    /**
     * Halaman Utama Kelola Zona Meja (Interactive Canvas Zone Drawer)
     */
    public function index()
    {
        $zones = WorkstationZone::with('employee')->get();
        $employees = Employee::whereNull('assigned_zone_id')->get();

        return view('admin.zones', compact('zones', 'employees'));
    }

    /**
     * Proxy Snapshot Frame JPEG dari Python AI Engine
     */
    public function getSnapshot()
    {
        try {
            $response = Http::timeout(2)->get($this->pythonApiUrl . '/api/snapshot');
            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', 'image/jpeg')
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            }
        } catch (\Exception $e) {
            // Fallback placeholder jika Python engine belum aktif
        }

        // Return blank placeholder image
        $im = imagecreatetruecolor(640, 480);
        $bg = imagecolorallocate($im, 15, 23, 42);
        $textColor = imagecolorallocate($im, 148, 163, 184);
        imagefilledrectangle($im, 0, 0, 640, 480, $bg);
        imagestring($im, 4, 180, 230, "Menghubungkan ke CCTV Stream...", $textColor);

        ob_start();
        imagejpeg($im);
        $imageData = ob_get_clean();
        imagedestroy($im);

        return response($imageData, 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Simpan Semua Zona Meja (Bulk Save dari Canvas Interactive)
     */
    public function saveAllZones(Request $request)
    {
        $request->validate([
            'zones' => 'required|array',
            'zones.*.id' => 'required|string',
            'zones.*.zone_name' => 'nullable|string',
            'zones.*.bbox' => 'required|array|size:4',
        ]);

        $inputZones = $request->input('zones');
        $validZoneIds = [];
        $configChairZones = [];

        foreach ($inputZones as $index => $z) {
            $zoneId = trim($z['id']);
            $zoneName = !empty($z['zone_name']) ? trim($z['zone_name']) : ('Meja ' . ($index + 1));
            $bbox = [
                intval($z['bbox'][0]),
                intval($z['bbox'][1]),
                intval($z['bbox'][2]),
                intval($z['bbox'][3]),
            ];

            $validZoneIds[] = $zoneId;
            $configChairZones[] = [
                'id' => $zoneId,
                'bbox' => $bbox
            ];

            WorkstationZone::updateOrCreate(
                ['zone_id' => $zoneId],
                [
                    'zone_name' => $zoneName,
                    'bbox_x1' => $bbox[0],
                    'bbox_y1' => $bbox[1],
                    'bbox_x2' => $bbox[2],
                    'bbox_y2' => $bbox[3],
                ]
            );
        }

        // Hapus zona dari DB yang tidak ada di daftar input
        WorkstationZone::whereNotIn('zone_id', $validZoneIds)->delete();

        // Sync ke config.json Python Engine
        $this->syncToConfigJson($configChairZones);

        // Panggil reload_zones ke Python AI engine
        try {
            Http::timeout(2)->post($this->pythonApiUrl . '/api/reload_zones');
        } catch (\Exception $e) {
            // Engine mungkin belum aktif, tetap simpan di config.json
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Zona meja kerja berhasil disimpan & disinkronkan ke AI Engine!',
            'total_zones' => count($configChairZones)
        ]);
    }

    /**
     * Hapus Satu Zona Meja
     */
    public function destroy($zoneId)
    {
        $zone = WorkstationZone::find($zoneId);
        if ($zone) {
            $zone->delete();
        }

        // Re-sync all remaining zones to config.json
        $remainingZones = WorkstationZone::all();
        $configChairZones = [];
        foreach ($remainingZones as $z) {
            $configChairZones[] = [
                'id' => $z->zone_id,
                'bbox' => [$z->bbox_x1, $z->bbox_y1, $z->bbox_x2, $z->bbox_y2]
            ];
        }

        $this->syncToConfigJson($configChairZones);

        try {
            Http::timeout(2)->post($this->pythonApiUrl . '/api/reload_zones');
        } catch (\Exception $e) {}

        return redirect()->route('admin.zones')->with('success', "Zona '{$zoneId}' berhasil dihapus.");
    }

    /**
     * Helper: Update chair_zones di monitor/config.json
     */
    private function syncToConfigJson(array $chairZones)
    {
        $configPaths = [
            base_path('monitor/config.json'),
            base_path('config.json'),
            'd:/monitor/config.json'
        ];

        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                $content = json_decode(file_get_contents($path), true);
                if (is_array($content)) {
                    $content['chair_zones'] = $chairZones;
                    file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT));
                }
            }
        }
    }
}
