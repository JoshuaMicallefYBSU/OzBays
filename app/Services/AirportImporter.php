<?php

namespace App\Services;

use App\Models\Airports;
use App\Models\BayAllocations;
use App\Models\BayConflicts;
use App\Models\Bays;
use App\Models\FlightLiveBays;
use App\Models\FlightLiveMissingBays;
use App\Models\Flights;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AirportImporter
{
    // Folder holding the site's airport files - one JSON file per airport (e.g. YMML.json).
    // Kept in storage so it isn't publicly downloadable or tracked by git - the copies in
    // public/config/airports on GitHub are for reference only and are never read by the site.
    public static function directory(): string
    {
        return storage_path('app/airports');
    }

    /**
     * Check an airport JSON object has everything the importer needs.
     * Returns a list of problems - empty means it's valid.
     */
    public function validate($airport): array
    {
        if (! is_array($airport)) {
            return ['File is not a valid JSON airport object.'];
        }

        $errors = [];

        if (! isset($airport['icao']) || ! preg_match('/^[A-Z]{4}$/', (string) $airport['icao'])) {
            $errors[] = '"icao" must be a 4 letter uppercase ICAO code.';
        }

        foreach (['name', 'lat', 'lon'] as $field) {
            if (! isset($airport[$field]) || $airport[$field] === '') {
                $errors[] = "\"{$field}\" is missing.";
            }
        }

        if (empty($airport['parking']) || ! is_array($airport['parking'])) {
            $errors[] = '"parking" must contain at least one bay.';

            return $errors;
        }

        foreach ($airport['parking'] as $bayCode => $bay) {
            foreach (['lat', 'lon', 'AC'] as $field) {
                if (! is_array($bay) || ! isset($bay[$field]) || $bay[$field] === '') {
                    $errors[] = "Bay {$bayCode} is missing \"{$field}\".";
                }
            }
        }

        return $errors;
    }

    /**
     * Create/update the airport and all of its bays in the database, and remove any of
     * the airport's bays that are no longer in the file.
     */
    public function import(array $airport): void
    {
        $icao = $airport['icao'];

        Airports::updateOrCreate(['icao' => $icao], $this->airportAttributes($airport) + ['check_exist' => 1]);

        foreach ($this->bayAttributes($airport) as $bayCode => $attributes) {
            Bays::updateOrCreate(['airport' => $icao, 'bay' => $bayCode], $attributes + ['check_exist' => 1]);
        }

        // Delete Bays no longer in the airport's file
        $removed = Bays::where('airport', $icao)->whereNotIn('bay', array_map('strval', array_keys($airport['parking'])))->get();
        foreach ($removed as $bay) {
            $bay->delete();
        }
    }

    /**
     * Remove an airport from OzBays - its bays, everything pointing at them, and its site file (otherwise the hourly
     * AerodromeUpdates sync would import it straight back). Flight logs are kept as history.
     */
    public function delete(string $icao): void
    {
        DB::transaction(function () use ($icao) {
            $bayIds = Bays::where('airport', $icao)->pluck('id');

            BayConflicts::whereIn('bay', $bayIds)->delete();
            BayAllocations::whereIn('bay', $bayIds)->orWhere('airport', $icao)->delete();
            Flights::whereIn('scheduled_bay', $bayIds)->update(['scheduled_bay' => null]);
            Flights::whereIn('current_bay', $bayIds)->update(['current_bay' => null]);
            FlightLiveBays::where('airport', $icao)->orWhereIn('scheduled_bay', $bayIds)->delete();
            FlightLiveMissingBays::where('airport', $icao)->delete();

            Bays::where('airport', $icao)->delete();
            Airports::where('icao', $icao)->delete();
        });

        File::delete(self::directory().'/'.$icao.'.json');
    }

    // Labels for the fields shown when previewing an import
    public const AIRPORT_FIELDS = [
        'name' => 'Name', 'lat' => 'Latitude', 'lon' => 'Longitude', 'color' => 'Colour', 'status' => 'Status',
        'eibt_variable' => 'EIBT Multiplier', 'taxi_time' => 'Taxi Time', 'live_update_times' => 'Live Update Times (UTC)',
    ];

    public const BAY_FIELDS = [
        'long_name' => 'Long Name', 'lat' => 'Latitude', 'lon' => 'Longitude', 'aircraft' => 'Aircraft',
        'pax_type' => 'Type', 'operators' => 'Operators', 'priority' => 'Priority', 'terminal' => 'Terminal', 'gate' => 'Gate',
    ];

    /**
     * What importing this file would change, compared to what's in the database now:
     * ['new' => bool, 'airport' => [field => [old, new]], 'added' => [bay => attributes],
     *  'changed' => [bay => [field => [old, new]]], 'removed' => [bay => Bays]]
     */
    public function diff(array $airport): array
    {
        $current = Airports::where('icao', $airport['icao'])->first();
        $incomingBays = $this->bayAttributes($airport);
        $currentBays = Bays::where('airport', $airport['icao'])->get()->keyBy(fn ($bay) => (string) $bay->bay);

        $airportChanges = [];
        foreach ($this->airportAttributes($airport) as $field => $value) {
            if ($current === null || ! $this->sameValue($current->{$field}, $value)) {
                $airportChanges[$field] = [$current?->{$field}, $value];
            }
        }

        $added = [];
        $changed = [];
        foreach ($incomingBays as $bayCode => $attributes) {
            $existing = $currentBays->get($bayCode);

            if ($existing === null) {
                $added[$bayCode] = $attributes;
                continue;
            }

            foreach ($attributes as $field => $value) {
                if (! $this->sameValue($existing->{$field}, $value)) {
                    $changed[$bayCode][$field] = [$existing->{$field}, $value];
                }
            }
        }

        $removed = $currentBays->reject(fn ($bay, $bayCode) => isset($incomingBays[$bayCode]))->all();

        return [
            'new' => $current === null,
            'airport' => $current === null ? [] : $airportChanges,
            'added' => $added,
            'changed' => $changed,
            'removed' => $removed,
            'bay_count' => count($incomingBays),
        ];
    }

    private function airportAttributes(array $airport): array
    {
        return [
            'name' => $airport['name'],
            'lat' => $airport['lat'],
            'lon' => $airport['lon'],
            'color' => $airport['settings']['color'] ?? 'purple',
            'status' => $airport['settings']['status'] ?? 'disabled',
            'eibt_variable' => $airport['settings']['eibt_config'] ?? 1.4,
            'taxi_time' => $airport['settings']['taxi_time'] ?? 15,
            'live_update_times' => $airport['settings']['update_time_utc'] ?? '0,2,4,6,8,10,12,14,20,22,24',
        ];
    }

    // Database attributes for every bay in the file, keyed by bay code
    private function bayAttributes(array $airport): array
    {
        $longNames = is_array($airport['long_name'] ?? null) ? $airport['long_name'] : [];
        $bays = [];

        foreach ($airport['parking'] as $bayCode => $bay) {
            $bays[(string) $bayCode] = [
                'long_name' => $this->longName((string) $bayCode, $longNames),
                'lat'       => $bay['lat'],
                'lon'       => $bay['lon'],
                'aircraft'  => $bay['AC'],
                'pax_type'  => $bay['Type'] ?? null,
                'operators' => $bay['Operator'] ?? null,
                'priority'  => $bay['Priority'] ?? 5,
                'terminal'  => $bay['Terminal'] ?? null,
                // IRL gate(s) this stand is reached from - e.g. "2" or "1,2" (see LiveBaysJob gate groups)
                'gate'      => isset($bay['Gate']) && $bay['Gate'] !== '' ? (string) $bay['Gate'] : null,
            ];
        }

        return $bays;
    }

    // Database values come back as strings - compare numbers as numbers and treat null/"" as the same
    private function sameValue($old, $new): bool
    {
        if (($old === null || $old === '') && ($new === null || $new === '')) {
            return true;
        }

        if (is_numeric($old) && is_numeric($new)) {
            return abs((float) $old - (float) $new) < 0.0000001;
        }

        return (string) $old === (string) $new;
    }

    /**
     * Resolve a bay's long name from the airport's "long_name" section.
     * A key matching the whole bay wins, otherwise the letters in front of the bay number are
     * swapped for their name - e.g. {"D": "Domestic"} turns D55A into "Domestic 55A".
     */
    public function longName(string $bayCode, array $longNames): ?string
    {
        if (isset($longNames[$bayCode])) {
            return $longNames[$bayCode];
        }

        if (! preg_match('/^([A-Za-z]+)(\d.*)$/', $bayCode, $m) || ! isset($longNames[$m[1]])) {
            return null;
        }

        return $longNames[$m[1]].' '.$m[2];
    }
}
