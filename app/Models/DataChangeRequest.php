<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataChangeRequest extends Model
{
    protected $fillable = [
        'type', 'target', 'payload', 'status', 'submitted_by', 'reviewed_by',
        'review_note', 'reviewed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
