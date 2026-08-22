<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $fillable = [
        'name',
        'code',
        'description',
        'cctv_source',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function zones()
    {
        return $this->hasMany(WorkstationZone::class, 'room_id', 'id');
    }
}
