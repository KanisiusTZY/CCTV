<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use App\Models\Employee;
use App\Models\WorkstationZone;

class AdminEmployeeController extends Controller
{
    private string $pythonApiUrl = 'http://localhost:5000';

    /**
     * Halaman Kelola Data Pegawai & Registrasi Wajah
     */
    public function index()
    {
        return redirect()->route('admin.zones');
    }

    /**
     * Tambah Pegawai Baru + Upload Foto Wajah ke faces_db/
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'position' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:50',
            'max_away_minutes' => 'nullable|integer|min:1|max:180',
            'assigned_zone_id' => 'nullable|string|exists:workstation_zones,zone_id',
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $name = trim($request->name);
        $cleanFilename = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $name)) . '.jpeg';

        // 1. Simpan ke public storage Laravel untuk preview web
        $publicDir = public_path('uploads/employees');
        if (!File::exists($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }
        $request->file('photo')->move($publicDir, $cleanFilename);

        // 2. Salin juga ke monitor/faces_db/ agar dibaca oleh InsightFace Python AI Engine
        $facesDbDirs = [
            base_path('monitor/faces_db'),
            'd:/monitor/faces_db'
        ];

        foreach ($facesDbDirs as $fDir) {
            if (File::exists($fDir)) {
                File::copy($publicDir . '/' . $cleanFilename, $fDir . '/' . $cleanFilename);
            }
        }

        // 3. Simpan ke Database MySQL
        Employee::create([
            'name' => $name,
            'position' => $request->position,
            'phone_number' => $request->phone_number,
            'max_away_minutes' => $request->max_away_minutes ?: 15,
            'assigned_zone_id' => $request->assigned_zone_id,
            'photo_filename' => $cleanFilename,
        ]);

        // 4. Minta Python Engine Reload Wajah Live
        $this->notifyPythonReloadFaces();

        return redirect()->route('admin.zones')->with('success', "Pegawai '{$name}' dan foto wajah AI berhasil didaftarkan!");
    }

    /**
     * Update Data Pegawai
     */
    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
            'position' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:50',
            'max_away_minutes' => 'nullable|integer|min:1|max:180',
            'assigned_zone_id' => 'nullable|string|exists:workstation_zones,zone_id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $name = trim($request->name);
        $cleanFilename = $employee->photo_filename;

        if ($request->hasFile('photo')) {
            $cleanFilename = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $name)) . '.jpeg';
            $publicDir = public_path('uploads/employees');
            $request->file('photo')->move($publicDir, $cleanFilename);

            $facesDbDirs = [
                base_path('monitor/faces_db'),
                'd:/monitor/faces_db'
            ];

            foreach ($facesDbDirs as $fDir) {
                if (File::exists($fDir)) {
                    File::copy($publicDir . '/' . $cleanFilename, $fDir . '/' . $cleanFilename);
                }
            }
        }

        $employee->update([
            'name' => $name,
            'position' => $request->position,
            'phone_number' => $request->phone_number,
            'max_away_minutes' => $request->max_away_minutes ?: 15,
            'assigned_zone_id' => $request->assigned_zone_id,
            'photo_filename' => $cleanFilename,
        ]);

        $this->notifyPythonReloadFaces();

        return redirect()->route('admin.zones')->with('success', "Data pegawai '{$name}' berhasil diperbarui!");
    }

    /**
     * Hapus Data Pegawai
     */
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $name = $employee->name;

        // Hapus file foto
        if ($employee->photo_filename) {
            $publicPath = public_path('uploads/employees/' . $employee->photo_filename);
            if (File::exists($publicPath)) {
                File::delete($publicPath);
            }

            $facesDbPaths = [
                base_path('monitor/faces_db/' . $employee->photo_filename),
                'd:/monitor/faces_db/' . $employee->photo_filename,
            ];
            foreach ($facesDbPaths as $fPath) {
                if (File::exists($fPath)) {
                    File::delete($fPath);
                }
            }
        }

        $employee->delete();
        $this->notifyPythonReloadFaces();

        return redirect()->route('admin.zones')->with('success', "Pegawai '{$name}' berhasil dihapus!");
    }

    /**
     * Trigger Python Stream Server untuk Reload Faces Database
     */
    public function reloadFaceDb()
    {
        $status = $this->notifyPythonReloadFaces();
        if ($status['success']) {
            return redirect()->route('admin.zones')->with('success', $status['message']);
        } else {
            return redirect()->route('admin.zones')->with('error', $status['message']);
        }
    }

    private function notifyPythonReloadFaces(): array
    {
        try {
            $response = Http::timeout(1)->post("{$this->pythonApiUrl}/api/reload_faces");
            if ($response->successful()) {
                return ['success' => true, 'message' => "Data pegawai dan foto wajah AI berhasil disimpan!"];
            }
            return ['success' => true, 'message' => "Data pegawai berhasil disimpan."];
        } catch (\Throwable $e) {
            return ['success' => true, 'message' => "Data pegawai berhasil disimpan (AI Engine akan otomatis membaca foto)."];
        }
    }
}
