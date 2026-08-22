<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\WorkstationZone;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $room1 = Room::updateOrCreate(
            ['code' => 'ruang-it'],
            [
                'name' => 'Ruang Kerja IT & Developer',
                'description' => 'Ruang kerja tim software engineer, IT infrastructure & AI research.',
                'cctv_source' => 'h.mp4',
                'is_active' => true,
            ]
        );

        $room2 = Room::updateOrCreate(
            ['code' => 'ruang-multimedia'],
            [
                'name' => 'Ruang Multimedia & Kreatif',
                'description' => 'Studio produksi konten, video editing, dan desain grafis.',
                'cctv_source' => 'f.mp4',
                'is_active' => true,
            ]
        );

        $room3 = Room::updateOrCreate(
            ['code' => 'ruang-admin'],
            [
                'name' => 'Ruang Administrasi & HRD',
                'description' => 'Ruang operasional kantor, manajemen data SDM, dan keuangan.',
                'cctv_source' => '2.mp4',
                'is_active' => true,
            ]
        );

        // Assign existing workstation zones to Room 1 by default
        WorkstationZone::whereNull('room_id')->update(['room_id' => $room1->id]);
    }
}
