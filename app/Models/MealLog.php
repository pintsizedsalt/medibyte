<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealLog extends Model
{
    protected $fillable = ['user_id', 'meal_type', 'food_name', 'calories', 'log_date'];
}
