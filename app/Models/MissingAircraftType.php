<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissingAircraftType extends Model
{
    protected $fillable = [
        'type',
        'count',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    // Records a miss for this type, incrementing its count if it's already
    // been seen before.
    public static function recordMiss(string $type): void
    {
        $missing = static::firstOrNew(['type' => $type]);
        $missing->count = ($missing->count ?? 0) + 1;
        $missing->last_seen_at = now();
        $missing->save();
    }
}
