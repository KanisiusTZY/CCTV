<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyZoneSummary extends Model
{
    use HasFactory;

    protected $table = 'daily_zone_summary';
    protected $primaryKey = 'summary_id';
    public $timestamps = false;

    protected $fillable = [
        'date',
        'zone_id',
        'total_working_seconds',
        'total_away_seconds',
        'last_updated',
    ];

    public function zone()
    {
        return $this->belongsTo(WorkstationZone::class, 'zone_id', 'zone_id');
    }

    public function getFormattedWorkingTimeAttribute()
    {
        $sec = $this->total_working_seconds ?? 0;
        $hours = floor($sec / 3600);
        $minutes = floor(($sec % 3600) / 60);
        $seconds = $sec % 60;
        return sprintf('%02dH %02dM %02dS', $hours, $minutes, $seconds);
    }
}
