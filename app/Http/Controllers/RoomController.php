<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Room;
use App\Models\WorkstationZone;

class RoomController extends Controller
{
    private string $pythonApiUrl = 'http://localhost:5000';

    /**
     * Portal Halaman Pilih Ruangan
     */
    public function index()
    {
        $rooms = Room::with(['zones.employee'])->get();
        $activeRoomId = session('active_room_id', 1);

        return view('rooms.index', compact('rooms', 'activeRoomId'));
    }

    /**
     * Memilih Ruangan Aktif & Mengarahkan ke Dashboard
     */
    public function selectRoom($id)
    {
        $room = Room::findOrFail($id);

        // Simpan Ruangan Aktif di Session
        session([
            'active_room_id' => $room->id,
            'active_room_name' => $room->name,
            'active_room_source' => $room->cctv_source
        ]);

        // Kirim perintah ganti sumber video CCTV ke Python AI Engine
        if (!empty($room->cctv_source)) {
            try {
                Http::timeout(2)->post($this->pythonApiUrl . '/api/set_source?source=' . urlencode($room->cctv_source));
            } catch (\Exception $e) {
                // Abaikan jika AI Engine belum aktif
            }
        }

        return redirect()->route('presence.dashboard')->with('success', "Berhasil masuk ke {$room->name}!");
    }

    /**
     * Tambah Ruangan Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'cctv_source' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $code = Str::slug($request->name);
        // Pastikan code unik
        $existing = Room::where('code', $code)->count();
        if ($existing > 0) {
            $code .= '-' . time();
        }

        $room = Room::create([
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
            'cctv_source' => $request->cctv_source,
            'is_active' => true,
        ]);

        return redirect()->route('rooms.index')->with('success', "Ruangan '{$room->name}' berhasil ditambahkan!");
    }

    /**
     * Hapus Ruangan
     */
    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $roomName = $room->name;
        $room->delete();

        if (session('active_room_id') == $id) {
            session()->forget(['active_room_id', 'active_room_name', 'active_room_source']);
        }

        return redirect()->route('rooms.index')->with('success', "Ruangan '{$roomName}' berhasil dihapus.");
    }
}
