<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresenceNotificationLog extends Model
{
    use HasFactory;

    protected $table = 'presence_notification_logs';

    protected $fillable = [
        'employee_id',
        'zone_id',
        'phone_number',
        'notification_type',
        'message',
        'status',
        'away_duration_minutes',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}