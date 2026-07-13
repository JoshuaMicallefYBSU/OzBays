<?php

namespace App\Http\Controllers;

use App\Models\Changelog;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    public function index()
    {
        $entries = Changelog::latest('merged_at')->get();

        return view('changelog.index', compact('entries'));
    }

    public function store(Request $request)
    {
        $token = config('services.changelog.token');

        if (empty($token) || !hash_equals($token, (string) $request->header('X-Changelog-Token'))) {
            abort(403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'url' => 'required|url|max:255',
            'pr_number' => 'nullable|integer',
            'author' => 'nullable|string|max:255',
            'merged_at' => 'nullable|date',
        ]);

        $data['merged_at'] ??= now();

        $entry = !empty($data['pr_number'])
            ? Changelog::updateOrCreate(['pr_number' => $data['pr_number']], $data)
            : Changelog::create($data);

        return response()->json($entry, 201);
    }
}
