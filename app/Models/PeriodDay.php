<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodDay extends Model
{
    /** The flow readings a daily log accepts, lightest first. */
    public const FLOWS = ['spotting', 'light', 'medium', 'heavy'];

    /** The discharge types a daily log accepts. */
    public const DISCHARGES = ['none', 'creamy', 'egg_white', 'watery', 'sticky'];

    /** The at-home test results a daily log accepts. */
    public const TEST_RESULTS = ['negative', 'positive'];

    /** The ovulation test adds a third reading on top of the shared two. */
    public const LH_RESULTS = ['negative', 'positive', 'peak'];

    protected $fillable = [
        'user_id', 'log_date', 'flow', 'pain', 'symptoms', 'mood', 'discharge',
        'bbt', 'lh_test', 'pregnancy_test', 'medication', 'sexual_activity',
        'protection', 'notes',
    ];

    protected $casts = [
        'log_date' => 'date:Y-m-d',
        'pain' => 'integer',
        'symptoms' => 'array',
        'bbt' => 'float',
        'sexual_activity' => 'boolean',
        'protection' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
