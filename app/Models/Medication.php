<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    /**
     * The units a dose can be measured in, grouped the way a clinician reads a
     * chart: mass, volume, concentration, then counted forms.
     *
     * @var array<int, string>
     */
    public const UNITS = [
        'mcg', 'mg', 'g', 'mL', 'L', 'IU', 'mg/mL',
        'tablet', 'capsule', 'puff', 'spray', 'drop', 'patch',
    ];

    protected $fillable = [
        'user_id', 'name', 'dosage', 'dosage_amount', 'dosage_unit', 'food_instruction', 'times',
        'start_date', 'end_date', 'notes', 'is_active',
    ];

    protected $casts = [
        'times' => 'array',
        'dosage_amount' => 'float',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    /**
     * The dose as every screen, reminder and the weekly PDF reads it. Padding
     * zeros come off so a stored 10.000 shows as "10 mg" and 0.250 as "0.25 mg".
     */
    public static function dosageText(float|int|string|null $amount, ?string $unit): ?string
    {
        $parts = [];

        if ($amount !== null && $amount !== '') {
            $parts[] = rtrim(rtrim(number_format((float) $amount, 3, '.', ''), '0'), '.');
        }

        if ($unit) {
            $parts[] = $unit;
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}
