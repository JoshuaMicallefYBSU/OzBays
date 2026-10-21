<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FeedbackController extends Controller
{
    ### USER - Provide Feedback
    public function create()
    {
        return view('feedback.create', ['questions' => Feedback::QUESTIONS]);
    }

    public function store(Request $request, HtmlSanitizer $sanitizer)
    {
        $rules = [
            'flight' => 'nullable|string|max:100',
            'body'   => 'required|string|max:20000',
        ];
        foreach (array_keys(Feedback::QUESTIONS) as $key) {
            $rules["ratings.{$key}"] = 'required|integer|between:1,10';
        }

        $data = $request->validate($rules, ['body.required' => 'Please add some written feedback.']);

        $body = $sanitizer->clean($data['body']);
        if (trim(strip_tags($body)) === '') {
            return back()->withInput()->withErrors(['body' => 'Please add some written feedback.']);
        }

        $feedback = Feedback::create([
            'user_id' => auth()->id(),
            'ratings' => array_map('intval', array_intersect_key($data['ratings'], Feedback::QUESTIONS)),
            'flight'  => $data['flight'] ?? null,
            'body'    => $body,
        ]);

        $this->notifyDiscord($feedback);

        return redirect()->route('dashboard.index')->with('success', 'Thanks for your feedback! It has been sent to the OzBays team.');
    }

    ### ADMIN - View Feedback
    public function index()
    {
        $feedback = Feedback::with('user')->latest()->get();

        return view('dashboard.admin.feedback.index', ['feedback' => $feedback, 'questions' => Feedback::QUESTIONS]);
    }

    public function show(Feedback $feedback)
    {
        return view('dashboard.admin.feedback.show', ['feedback' => $feedback->load('user'), 'questions' => Feedback::QUESTIONS]);
    }

    // Posts a summary to the feedback Discord webhook, with a link for developers to view it in full
    private function notifyDiscord(Feedback $feedback): void
    {
        $webhook = config('services.discord.feedback_webhook');
        if (empty($webhook)) {
            return;
        }

        $user = $feedback->user;
        $fields = [[
            'name'   => 'Submitted By',
            'value'  => ($user?->fullName('FL') ?: 'Unknown').' ('.$feedback->user_id.')',
            'inline' => true,
        ]];

        if ($feedback->flight) {
            $fields[] = ['name' => 'Flight', 'value' => Str::limit($feedback->flight, 100), 'inline' => true];
        }

        $fields[] = ['name' => 'Average', 'value' => $feedback->averageRating().'/10', 'inline' => true];

        $scores = collect(Feedback::QUESTIONS)
            ->map(fn ($question, $key) => "**".($feedback->ratings[$key] ?? '-')."/10** - {$question}")
            ->implode("\n");
        $fields[] = ['name' => 'Scores', 'value' => Str::limit($scores, 1000)];

        $preview = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h3>'], ' ', $feedback->body)))));

        try {
            Http::timeout(5)->post($webhook, [
                'username' => 'OzBays Feedback',
                'embeds'   => [[
                    'title'       => 'New Feedback #'.$feedback->id,
                    'url'         => route('dashboard.feedback.show', $feedback),
                    'description' => Str::limit($preview, 600)."\n\n[View full feedback](".route('dashboard.feedback.show', $feedback).')',
                    'color'       => 0xFFC72C,
                    'fields'      => $fields,
                    'timestamp'   => $feedback->created_at->toIso8601String(),
                ]],
            ])->throw();
        } catch (\Throwable $e) {
            // Feedback is already saved - a webhook failure shouldn't lose it or error for the user
            Log::error('Feedback webhook failed for feedback #'.$feedback->id.': '.$e->getMessage());
        }
    }
}
