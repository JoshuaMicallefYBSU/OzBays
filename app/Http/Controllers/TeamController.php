<?php

namespace App\Http\Controllers;

use App\Models\User;

class TeamController extends Controller
{
    public function index()
    {
        $leadership = User::role(['Lead Developer', 'Developer'])->get()
            ->sortByDesc(fn (User $user) => $user->hasRole('Lead Developer'))
            ->values();

        $community = User::role(['Maintainer', 'Contributor'])->get()
            ->sortByDesc(fn (User $user) => $user->hasRole('Maintainer'))
            ->values();

        return view('team.index', compact('leadership', 'community'));
    }
}
