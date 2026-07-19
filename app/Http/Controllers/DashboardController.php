<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\MissingAircraftType;
use App\Models\User;
use App\Models\UserPreference;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
    }

    public function settingsView()
    {
        return view('dashboard.settings.index');
    }

    public function settingsSave(Request $request)
    {
        $data = $request->validate([
            'name_format' => 'required|integer|between:0,3',
            'hoppie_usage' => 'required|boolean',
            'email_feedback' => 'required|boolean',
            'news_notifications' => 'required|boolean',
        ]);

        // Always the authenticated user's own preferences - never an id from
        // the request body.
        $preferences = $request->user()->getUserPreferencesOrCreate();
        $preferences->fill($data);
        $preferences->save();

        return back()->with('success', 'Success!!! Your settings where updated!');
    }

    ################## ADMIN SECTION
    public function airportList()
    {
        $airports = Airports::all();

        return view('dashboard.admin.airport.airport-list', compact('airports'));
    }

    public function airportView($icao)
    {
        $airport = Airports::where('icao', $icao)->first();

        if($airport == null){
            return redirect()->route('dashboard.admin.airport.all')->with('error', 'No airport definition has been made for '.$icao.'. Please select from the below airport options.');
        }

        return view('dashboard.admin.airport.airport-view', compact('airport'));
    }

    public function bayView($icao, $bay_url)
    {
        $bay = Bays::where('bay', $bay_url)->where('airport', $icao)->first();

        if($bay == null){
            return redirect()->route('dashboard.admin.airport.view', [$icao])->with('error', 'Bay '.$bay_url.' does not exist at '.$icao.'. Please select from the below bay options.');
        }

        return view('dashboard.admin.airport.bay-view', compact('bay'));
    }

    public function userList()
    {
        $users = User::all();

        return view('dashboard.admin.user.index', compact('users'));
    }

    public function userView(User $user)
    {
        $roles = Role::orderBy('name')->get();

        return view('dashboard.admin.user.view', compact('user', 'roles'));
    }

    public function userAssignRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->assignRole($request->role);

        return back()->with('success', 'Role "'.$request->role.'" assigned to '.$user->fullName('FL').'.');
    }

    public function userRemoveRole(Request $request, User $user, string $role)
    {
        $user->removeRole($role);

        return back()->with('success', 'Role "'.$role.'" removed from '.$user->fullName('FL').'.');
    }

    // Look up the airport referenced by an admin toggle, 404-safe.
    private function findAirportOrAbort(Request $request): Airports
    {
        $request->validate(['icao' => 'required|string']);

        return Airports::where('icao', $request->icao)->firstOrFail();
    }

    // Disable Airport Function
    public function disableAirport(Request $request)
    {
        $airport = $this->findAirportOrAbort($request);

        $airport->status = 'testing';
        $airport->save();

        return back()->with('success', 'Airport has been set to testing mode - uplinks now go to testers only.');
    }

    // Activate Airport Function
    public function activateAirport(Request $request)
    {
        $airport = $this->findAirportOrAbort($request);

        $airport->status = 'active';
        $airport->save();

        return back()->with('success', 'Airport has successfully been activated! - YeeHaw!!!!');
    }

    // Disable Airport Function
    public function disableLiveAirport(Request $request)
    {
        $airport = $this->findAirportOrAbort($request);

        $airport->live_bays = 0;
        $airport->save();

        return back()->with('success', 'Airport Live Bay has successfully been disabled!');
    }

    // Activate Airport Function
    public function activateLiveAirport(Request $request)
    {
        $airport = $this->findAirportOrAbort($request);

        $airport->live_bays = 1;
        $airport->save();

        return back()->with('success', 'Airport Live Bay has successfully been activated! - YeeHaw!!!!');
    }

    // Aircraft.json view
    public function aircraftList()
    {
            $path = public_path('config/aircraft.json');

        if (!File::exists($path)) {
            abort(404, 'aircraft.json not found.');
        }

        $raw = json_decode(File::get($path), true);

        if (!is_array($raw)) {
            abort(500, 'aircraft.json is not valid JSON.');
        }

        $groups = [];

        foreach ($raw as $key => $value) {
            // Ignore AllocationInfo_General, AllocationInfo_General2, etc
            if (str_starts_with($key, 'AllocationInfo_General')) {
                continue;
            }

            // If this key is an AllocationInfo_* description, attach it to the group
            if (str_starts_with($key, 'AllocationInfo_')) {
                $groupKey = substr($key, strlen('AllocationInfo_')); // e.g. GA, 1, 2, etc

                // Only store description if it's not General* (already skipped above)
                $groups[$groupKey] ??= [
                    'key' => $groupKey,
                    'description' => null,
                    'aircraft' => [],
                ];

                $groups[$groupKey]['description'] = is_string($value) ? $value : null;
                continue;
            }

            // Otherwise: if it's an array, it's the aircraft list for that group key
            if (is_array($value)) {
                $groups[$key] ??= [
                    'key' => $key,
                    'description' => null,
                    'aircraft' => [],
                ];

                $groups[$key]['aircraft'] = $value;
            }
        }

        // Optional: sort groups nicely (GA first, then numeric)
        uksort($groups, function ($a, $b) {
            if ($a === 'GA') return -1;
            if ($b === 'GA') return 1;

            $aNum = ctype_digit((string)$a);
            $bNum = ctype_digit((string)$b);

            if ($aNum && $bNum) return (int)$a <=> (int)$b;
            if ($aNum) return -1;
            if ($bNum) return 1;

            return strcmp((string)$a, (string)$b);
        });

        $missingTypes = MissingAircraftType::orderByDesc('count')->get();

        return view('dashboard.admin.aircraft.index', [
            'groups' => $groups,
            'missingTypes' => $missingTypes,
        ]);
    }
}
