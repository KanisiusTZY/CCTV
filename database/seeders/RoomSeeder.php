<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\WorkstationZone;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus ruangan dummy/duplikat lain agar hanya ada 1 ruangan utama
        Room::whereNotIn('code', ['ruang-it'])->delete();

        $room = Room::updateOrCreate(
            ['code' => 'ruang-it'],
            [
                'name' => 'Ruang Kerja IT & Developer',
                'description' => 'Monitoring zona meja kerja pegawai dan rekognisi wajah CCTV.',
                'cctv_source' => 'h.mp4',
                'is_active' => true,
            ]
        );

        // Assign semua zona meja kerja ke ruangan utama ini
        WorkstationZone::query()->update(['room_id' => $room->id]);
    }
}
