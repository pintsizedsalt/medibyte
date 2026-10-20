<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoodLog extends Model
{
    public const MOODS = [
    'happy', 'excited', 'grateful', 'calm', 'content', 'hopeful',
    'proud', 'loved', 'energetic', 'motivated', 'relaxed',
    'bored', 'tired', 'neutral', 'confused',
    'lonely', 'sad', 'anxious', 'stressed', 'overwhelmed',
    'angry', 'frustrated', 'irritated', 'disappointed',
    'guilty', 'embarrassed', 'scared', 'numb',
    ];

    protected $fillable = [
        'user_id', 'mood', 'intensity', 'cried',
        'has_big_event', 'event_note', 'impacts', 'reflection', 'log_date',
    ];

    protected $casts = [
        'impacts' => 'array',
        'cried' => 'boolean',
        'has_big_event' => 'boolean',
    ];
}
