<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealLog extends Model
{
    protected $fillable = ['user_id', 'meal_type', 'meal_time', 'food_name', 'calories', 'log_date'];

    protected $casts = [
        'calories' => 'integer',
        'meal_time' => 'string',
        'log_date' => 'date:Y-m-d',
    ];
}
