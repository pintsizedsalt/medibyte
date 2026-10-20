<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SleepLog extends Model
{
    public function sleepLogs()
    {
        return $this->hasMany(SleepLog::class);
    }
}
