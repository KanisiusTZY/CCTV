<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        Employee::updateOrCreate(
            ['name' => 'Bili'],
            [
                'photo_filename' => 'bili.jpeg',
                'position' => 'Staff IT',
                'assigned_zone_id' => 'chair_3'
            ]
        );

        Employee::updateOrCreate(
            ['name' => 'Gea'],
            [
                'photo_filename' => 'gea.jpeg',
                'position' => 'Staff Multimedia',
                'assigned_zone_id' => 'chair_1'
            ]
        );
    }
}
