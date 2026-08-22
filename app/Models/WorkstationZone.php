<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkstationZone extends Model
{
    use HasFactory;

    protected $table = 'workstation_zones';
    protected $primaryKey = 'zone_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'zone_id',
        'zone_name',
        'bbox_x1',
        'bbox_y1',
        'bbox_x2',
        'bbox_y2',
    ];

    public static function syncFromConfig(array $chairZones)
    {
        foreach ($chairZones as $z) {
            if (!isset($z['id']) || !isset($z['bbox'])) {
                continue;
            }
            $zoneId = $z['id'];
            $bbox = $z['bbox'];

            static::updateOrCreate(
                ['zone_id' => $zoneId],
                [
                    'zone_name' => 'Meja ' . str_replace('chair_', '', $zoneId),
                    'bbox_x1' => $bbox[0] ?? 0,
                    'bbox_y1' => $bbox[1] ?? 0,
                    'bbox_x2' => $bbox[2] ?? 0,
                    'bbox_y2' => $bbox[3] ?? 0,
                ]
            );
        }
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'assigned_zone_id', 'zone_id');
    }

    public function eventLogs()
    {
        return $this->hasMany(PresenceEventLog::class, 'zone_id', 'zone_id');
    }

    public function dailySummaries()
    {
        return $this->hasMany(DailyZoneSummary::class, 'zone_id', 'zone_id');
    }
}
