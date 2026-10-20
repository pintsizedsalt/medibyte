<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    protected $fillable = [
        'user_id', 'name', 'dosage', 'food_instruction', 'times',
        'start_date', 'end_date', 'notes', 'is_active',
    ];

    protected $casts = [
    'times' => 'array',
    'start_date' => 'date:Y-m-d',
    'end_date' => 'date:Y-m-d',
    'is_active' => 'boolean',
    ];

}
