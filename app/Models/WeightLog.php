<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeightLog extends Model
{
    protected $fillable = [
        'user_id',
        'weight_kg',
        'height_cm',
        'log_date',
    ];

    protected $casts = [
        'log_date' => 'date:Y-m-d',
    ];

    protected $appends = ['bmi'];

    public function getBmiAttribute($value)
    {
        if ($this->weight_kg && $this->height_cm) {
            $meters = $this->height_cm / 100;

            return round($this->weight_kg / ($meters * $meters), 2);
        }

        return null;
    }
}
