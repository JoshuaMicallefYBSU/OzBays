<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\FeedbackReply;
use App\Notifications\SystemNotification;
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

        return redirect()->route('feedback.my.show', $feedback)->with('success', 'Thanks for your feedback! It has been sent to the OzBays team.');
    }

    ### USER - My Feedback (view own submissions & reply)
    public function myIndex()
    {
        $feedback = Feedback::where('user_id', auth()->id())->latest()->get();

        return view('feedback.my-index', ['feedback' => $feedback]);
    }

    public function myShow(Feedback $feedback)
    {
        abort_unless($feedback->user_id === auth()->id(), 403);

        return view('feedback.my-show', [
            'feedback' => $feedback->load('replies.user'),
            'questions' => Feedback::QUESTIONS,
        ]);
    }

    public function userReply(Request $request, Feedback $feedback)
    {
        abort_unless($feedback->user_id === auth()->id(), 403);

        $data = $request->validate(['body' => 'required|string|max:5000']);

        $reply = $feedback->replies()->create([
            'user_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        $wasClosed = $feedback->isClosed();
        if ($wasClosed) {
            $feedback->update(['status' => 'open']);
        }

        $this->notifyDiscordOfReply($feedback, $reply, reopened: $wasClosed);

        return redirect()->route('feedback.my.show', $feedback)->with('success', 'Your reply has been sent.');
    }

    ### ADMIN - View Feedback
    public function index()
    {
        $feedback = Feedback::with('user')->latest()->get();

        return view('dashboard.admin.feedback.index', ['feedback' => $feedback, 'questions' => Feedback::QUESTIONS]);
    }

    public function show(Feedback $feedback)
    {
        return view('dashboard.admin.feedback.show', [
            'feedback' => $feedback->load('user', 'replies.user'),
            'questions' => Feedback::QUESTIONS,
        ]);
    }

    public function reply(Request $request, Feedback $feedback)
    {
        $data = $request->validate(['body' => 'required|string|max:5000']);

        $reply = $feedback->replies()->create([
            'user_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        $feedback->user?->notify(new SystemNotification(
            title: 'A developer replied to your feedback',
            message: Str::limit(strip_tags($reply->body), 150),
            url: route('feedback.my.show', $feedback),
        ));

        return back()->with('success', 'Reply sent.');
    }

    public function close(Feedback $feedback)
    {
        $feedback->update(['status' => 'closed']);

        $feedback->user?->notify(new SystemNotification(
            title: 'Your feedback ticket was closed',
            message: 'Feedback #'.$feedback->id.' has been marked as resolved. Reply to it any time to reopen.',
            url: route('feedback.my.show', $feedback),
        ));

        return back()->with('success', 'Feedback #'.$feedback->id.' has been closed.');
    }

    public function reopen(Feedback $feedback)
    {
        $feedback->update(['status' => 'open']);

        return back()->with('success', 'Feedback #'.$feedback->id.' has been reopened.');
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

    // Lets the dev team know (via the same feedback webhook) when a submitter replies,
    // since that's the existing channel developers already watch for feedback activity
    private function notifyDiscordOfReply(Feedback $feedback, FeedbackReply $reply, bool $reopened): void
    {
        $webhook = config('services.discord.feedback_webhook');
        if (empty($webhook)) {
            return;
        }

        $user = $feedback->user;

        try {
            Http::timeout(5)->post($webhook, [
                'username' => 'OzBays Feedback',
                'embeds'   => [[
                    'title'       => ($reopened ? 'Feedback #'.$feedback->id.' reopened' : 'New reply on Feedback #'.$feedback->id),
                    'url'         => route('dashboard.feedback.show', $feedback),
                    'description' => Str::limit($reply->body, 600)."\n\n[View full feedback](".route('dashboard.feedback.show', $feedback).')',
                    'color'       => $reopened ? 0xF87171 : 0xFFC72C,
                    'fields'      => [[
                        'name'   => 'From',
                        'value'  => ($user?->fullName('FL') ?: 'Unknown').' ('.$feedback->user_id.')',
                        'inline' => true,
                    ]],
                    'timestamp'   => $reply->created_at->toIso8601String(),
                ]],
            ])->throw();
        } catch (\Throwable $e) {
            Log::error('Feedback reply webhook failed for feedback #'.$feedback->id.': '.$e->getMessage());
        }
    }
}
