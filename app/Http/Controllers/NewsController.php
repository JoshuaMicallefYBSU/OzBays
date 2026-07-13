<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Models\NewsArticle;
use App\Models\User;
use App\Notifications\SystemNotification;

class NewsController extends Controller
{
    public function list()
    {
        $articles = NewsArticle::latest('id')->get();

        return view('news.index', compact('articles'));
    }

    public function index()
    {
        $articles = NewsArticle::latest('id')->get();

        return view('dashboard.admin.news.index', compact('articles'));
    }

    public function create()
    {
        return view('dashboard.admin.news.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('news', 'public');
        }

        $data['user_id'] = $request->user()->id;

        $article = NewsArticle::create($data);

        $recipients = User::where('deleted', false)
            ->where('id', '!=', $request->user()->id)
            ->whereHas('userPreferences', fn ($query) => $query->where('news_notifications', 1))
            ->get();

        Notification::send($recipients, new SystemNotification(
            title: 'New Article: ' . $article->subject,
            message: $article->excerpt,
            url: route('news.show', $article),
            icon: 'fa-newspaper',
        ));

        return redirect()->route('dashboard.admin.news.index')->with('success', 'News article published!');
    }

    public function edit(NewsArticle $news)
    {
        return view('dashboard.admin.news.edit', ['article' => $news]);
    }

    public function update(Request $request, NewsArticle $news)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($news->image);
            $data['image'] = $request->file('image')->store('news', 'public');
        }

        $news->update($data);

        return redirect()->route('dashboard.admin.news.index')->with('success', 'News article updated!');
    }

    public function destroy(NewsArticle $news)
    {
        Storage::disk('public')->delete($news->image);
        $news->delete();

        return redirect()->route('dashboard.admin.news.index')->with('success', 'News article deleted.');
    }

    public function show(NewsArticle $news)
    {
        return view('news.show', ['article' => $news]);
    }
}
