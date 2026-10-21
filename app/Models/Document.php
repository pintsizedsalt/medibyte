<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'original_name',
        'path',
        'mime_type',
        'size',
        'notes',
    ];

    protected $hidden = ['path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
