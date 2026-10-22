<?php

namespace App\Http\Controllers;

use App\Models\ContributorApplication;
use App\Models\User;
use Illuminate\Http\Request;

class ContributorApplicationController extends Controller
{
    // Anyone already on the team doesn't need to apply
    private const TEAM_ROLES = ['Lead Developer', 'Developer', 'Maintainer', 'Contributor'];

    public static function canApply(User $user): bool
    {
        return ! $user->hasAnyRole(self::TEAM_ROLES)
            && ! ContributorApplication::pending()->where('user_id', $user->id)->exists();
    }

    ### USER - Apply
    public function create()
    {
        $user = auth()->user();

        $pending = ContributorApplication::pending()->where('user_id', $user->id)->latest()->first();

        return view('dashboard.contribute', [
            'pending' => $pending,
            'onTeam'  => $user->hasAnyRole(self::TEAM_ROLES),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (! self::canApply($user)) {
            return redirect()->route('dashboard.contribute')->with('error', 'You already have an application in, or are already part of the team.');
        }

        $data = $request->validate([
            'description' => 'required|string|max:5000',
            'experience'  => 'nullable|string|max:5000',
        ]);

        ContributorApplication::create($data + ['user_id' => $user->id]);

        return redirect()->route('dashboard.index')->with('success', 'Application submitted! The team will be in touch.');
    }

    // Rescind a pending application - kept as 'withdrawn' so the team can still see it was made
    public function withdraw()
    {
        $application = ContributorApplication::pending()->where('user_id', auth()->id())->latest()->first();

        if ($application === null) {
            return redirect()->route('dashboard.index')->with('error', "You don't have an application waiting for review.");
        }

        $application->update(['status' => 'withdrawn', 'reviewed_at' => now()]);

        return redirect()->route('dashboard.index')->with('success', 'Your contributor application has been rescinded.');
    }

    public static function hasPendingApplication(User $user): bool
    {
        return ContributorApplication::pending()->where('user_id', $user->id)->exists();
    }

    ### ADMIN - Review
    public function index()
    {
        $pending = ContributorApplication::pending()->with('user')->oldest()->get();
        $reviewed = ContributorApplication::where('status', '!=', 'pending')->with('user', 'reviewer')->latest('reviewed_at')->limit(50)->get();

        return view('dashboard.admin.applications.index', compact('pending', 'reviewed'));
    }

    public function approve(ContributorApplication $application)
    {
        if ($application->status !== 'pending') {
            return back()->with('error', 'This application has already been reviewed.');
        }

        $application->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        $application->user?->assignRole('Contributor');

        return back()->with('success', 'Application approved - '.$application->user?->fullName('FL').' is now a Contributor.');
    }

    public function reject(ContributorApplication $application)
    {
        if ($application->status !== 'pending') {
            return back()->with('error', 'This application has already been reviewed.');
        }

        $application->update(['status' => 'rejected', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

        return back()->with('success', 'Application rejected.');
    }
}
