<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'name',
        'photo_filename',
        'assigned_zone_id',
        'position',
        'phone_number',
        'max_away_minutes',
    ];

    public function zone()
    {
        return $this->belongsTo(WorkstationZone::class, 'assigned_zone_id', 'zone_id');
    }

    public function notificationLogs()
    {
        return $this->hasMany(PresenceNotificationLog::class, 'employee_id');
    }
}