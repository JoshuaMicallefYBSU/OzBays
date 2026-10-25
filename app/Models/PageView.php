<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    // Append-only log - there's nothing to update after the fact.
    const UPDATED_AT = null;

    protected $fillable = [
        'page',
        'visitor_id',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
