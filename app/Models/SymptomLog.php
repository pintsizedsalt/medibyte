<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SymptomLog extends Model
{
    protected $fillable = ['user_id', 'illness_name', 'symptom', 'severity', 'notes', 'log_date'];
}
