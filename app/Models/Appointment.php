<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
    'user_id', 'doctor_name', 'specialty', 'contact_number', 'office_address',
    'hospital', 'appointment_at', 'reason', 'notes', 'status',
    ];

    protected $casts = [
        'appointment_at' => 'datetime',
    ];
}
