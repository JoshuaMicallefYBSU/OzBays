<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedback';

    // 1-10 slider questions on the feedback form, in display order
    public const QUESTIONS = [
        'enjoyment'   => 'Overall, how much do you enjoy using OzBays?',
        'bay_fit'     => 'How suitable were your assigned bays for your aircraft and airline?',
        'accuracy'    => 'How accurate was the information shown (bay status, ELT/EIBT, live map)?',
        'reliability' => 'How reliable has OzBays been? (1 = lots of bugs or errors, 10 = no issues)',
        'ease'        => 'How easy are the website and live map to use?',
        'recommend'   => 'How likely are you to recommend OzBays to other pilots and controllers?',
    ];

    protected $fillable = [
        'user_id',
        'ratings',
        'flight',
        'body',
        'status',
    ];

    protected $casts = [
        'ratings' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function replies()
    {
        return $this->hasMany(FeedbackReply::class)->orderBy('created_at');
    }

    public function averageRating(): ?float
    {
        $ratings = array_filter((array) $this->ratings, 'is_numeric');

        return $ratings ? round(array_sum($ratings) / count($ratings), 1) : null;
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
