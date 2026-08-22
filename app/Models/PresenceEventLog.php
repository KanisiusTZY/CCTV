<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresenceEventLog extends Model
{
    use HasFactory;

    protected $table = 'presence_event_logs';
    protected $primaryKey = 'log_id';
    public $timestamps = false;

    protected $fillable = [
        'zone_id',
        'track_id',
        'previous_status',
        'current_status',
        'confidence',
        'iou_score',
        'timestamp',
    ];

    public function zone()
    {
        return $this->belongsTo(WorkstationZone::class, 'zone_id', 'zone_id');
    }
}
