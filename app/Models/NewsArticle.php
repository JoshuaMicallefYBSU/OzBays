<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsArticle extends Model
{
    protected $fillable = [
        'subject',
        'excerpt',
        'body',
        'image',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getExcerptAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }

        return Str::limit(strip_tags($this->body), 140);
    }

    public function getAuthorAttribute(): string
    {
        if (!$this->user) {
            return 'Unknown';
        }

        return $this->user->fullName('F') . ', ' . $this->user->highestRole()->name;
    }

    public function getImageUrlAttribute(): string
    {
        if (!$this->image) {
            return asset('img/logo.png');
        }

        return asset('storage/' . $this->image);
    }

    public function getHasCustomImageAttribute(): bool
    {
        return !empty($this->image);
    }
}
