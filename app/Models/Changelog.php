<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use League\CommonMark\CommonMarkConverter;

class Changelog extends Model
{
    protected $fillable = [
        'title',
        'description',
        'url',
        'pr_number',
        'author',
        'merged_at',
    ];

    protected $casts = [
        'merged_at' => 'datetime',
    ];

    public function getDescriptionHtmlAttribute(): ?string
    {
        if (empty($this->description)) {
            return null;
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($this->description)->getContent();
    }
}
