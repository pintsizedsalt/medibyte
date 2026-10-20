<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterLog extends Model
{
    protected $fillable = ['user_id', 'amount_ml', 'log_date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
