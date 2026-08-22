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
        $employees = Employee::with('zone')->orderBy('name', 'asc')->get();
        $zones = WorkstationZone::all();

        return view('admin.employees', compact('employees', 'zones'));
    }

    /**
     * Tambah Pegawai Baru + Upload Foto Wajah ke faces_db/
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'position' => 'nullable|string|max:100',
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
            'assigned_zone_id' => $request->assigned_zone_id,
            'photo_filename' => $cleanFilename,
        ]);

        // 4. Minta Python Engine Reload Wajah Live
        $this->notifyPythonReloadFaces();

        return redirect()->route('admin.employees')->with('success', "Pegawai '{$name}' dan foto wajah berhasil didaftarkan ke AI Engine!");
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
            'assigned_zone_id' => 'nullable|string|exists:workstation_zones,zone_id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $name = trim($request->name);
        $employee->name = $name;
        $employee->position = $request->position;
        $employee->assigned_zone_id = $request->assigned_zone_id;

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

            $employee->photo_filename = $cleanFilename;
        }

        $employee->save();
        $this->notifyPythonReloadFaces();

        return redirect()->route('admin.employees')->with('success', "Data pegawai '{$name}' berhasil diperbarui.");
    }

    /**
     * Hapus Pegawai & Foto Wajah
     */
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $name = $employee->name;
        $filename = $employee->photo_filename;

        if ($filename) {
            $publicFile = public_path('uploads/employees/' . $filename);
            if (File::exists($publicFile)) {
                File::delete($publicFile);
            }

            $facesDbDirs = [
                base_path('monitor/faces_db'),
                'd:/monitor/faces_db'
            ];

            foreach ($facesDbDirs as $fDir) {
                $fPath = $fDir . '/' . $filename;
                if (File::exists($fPath)) {
                    File::delete($fPath);
                }
            }
        }

        $employee->delete();
        $this->notifyPythonReloadFaces();

        return redirect()->route('admin.employees')->with('success', "Pegawai '{$name}' dan foto wajah berhasil dihapus.");
    }

    /**
     * Manual Trigger Reload Face Database
     */
    public function reloadFaceDb()
    {
        $res = $this->notifyPythonReloadFaces();
        return redirect()->route('admin.employees')->with('success', 'Database wajah AI berhasil dimuat ulang!');
    }

    /**
     * Helper POST ke Python /api/reload_faces
     */
    private function notifyPythonReloadFaces()
    {
        try {
            $res = Http::timeout(3)->post($this->pythonApiUrl . '/api/reload_faces');
            return $res->json();
        } catch (\Exception $e) {
            return null;
        }
    }
}
