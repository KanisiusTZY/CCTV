<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkstationZone;

class WorkstationZoneSeeder extends Seeder
{
    public function run()
    {
        $zones = [
            ['zone_id' => 'chair_1', 'zone_name' => 'Meja Kerja 1', 'bbox_x1' => 440, 'bbox_y1' => 183, 'bbox_x2' => 526, 'bbox_y2' => 311],
            ['zone_id' => 'chair_2', 'zone_name' => 'Meja Kerja 2', 'bbox_x1' => 337, 'bbox_y1' => 170, 'bbox_x2' => 410, 'bbox_y2' => 286],
            ['zone_id' => 'chair_3', 'zone_name' => 'Meja Kerja 3', 'bbox_x1' => 203, 'bbox_y1' => 143, 'bbox_x2' => 282, 'bbox_y2' => 243],
            ['zone_id' => 'chair_4', 'zone_name' => 'Meja Kerja 4', 'bbox_x1' => 105, 'bbox_y1' => 271, 'bbox_x2' => 182, 'bbox_y2' => 368],
            ['zone_id' => 'chair_5', 'zone_name' => 'Meja Kerja 5', 'bbox_x1' => 185, 'bbox_y1' => 310, 'bbox_x2' => 285, 'bbox_y2' => 449],
            ['zone_id' => 'chair_6', 'zone_name' => 'Meja Kerja 6', 'bbox_x1' => 58, 'bbox_y1' => 191, 'bbox_x2' => 132, 'bbox_y2' => 264],
        ];

        foreach ($zones as $zone) {
            WorkstationZone::firstOrCreate(['zone_id' => $zone['zone_id']], $zone);
        }
    }
}
