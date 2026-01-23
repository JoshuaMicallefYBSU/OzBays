<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\Airports;
use App\Models\Bays;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
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
            return redirect()->route('dashboard.admin.airport.view', [$icao])->with('error', 'Bay '.$bay.' does not exist at '.$icao.'. Please select from the below bay options.');
        }

        return view('dashboard.admin.airport.bay-view', compact('bay'));
    }

    // Disable Airport Function
    public function disableAirport(Request $request)
    {
        $airport = Airports::where('icao', $request->icao)->first();

        $airport->status = 'testing';
        $airport->save();

        return back()->with('info', 'Airport has successfully been disabled!');
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

        return view('dashboard.admin.aircraft.index', [
            'groups' => $groups,
        ]);
    }
}
