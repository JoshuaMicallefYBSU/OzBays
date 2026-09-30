<?php

namespace App\Services;

use App\Models\Airports;
use App\Models\Bays;

class AirportImporter
{
    // Folder holding one JSON file per airport (e.g. YMML.json)
    public static function directory(): string
    {
        return public_path('config/airports');
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
     * Create/update the airport and all of its bays in the database.
     * Everything touched is flagged check_exist = 1 so AerodromeUpdates keeps it.
     */
    public function import(array $airport): void
    {
        $icao = $airport['icao'];

        Airports::updateOrCreate(['icao' => $icao], [
            'lat' => $airport['lat'],
            'lon' => $airport['lon'],
            'name' => $airport['name'],
            'color' => $airport['settings']['color'] ?? 'purple',
            'status' => $airport['settings']['status'] ?? 'disabled',
            'eibt_variable' => $airport['settings']['eibt_config'] ?? 1.4,
            'taxi_time' => $airport['settings']['taxi_time'] ?? 15,
            'live_type' => $airport['settings']['airport_type'] ?? null,
            'live_update_times' => $airport['settings']['update_time_utc'] ?? '0,2,4,6,8,10,12,14,20,22,24',
            'check_exist' => 1,
        ]);

        $longNames = is_array($airport['long_name'] ?? null) ? $airport['long_name'] : [];

        foreach ($airport['parking'] as $bayCode => $bay) {
            Bays::updateOrCreate(['airport' => $icao, 'bay' => $bayCode], [
                'long_name' => $this->longName((string) $bayCode, $longNames),
                'lat'       => $bay['lat'],
                'lon'       => $bay['lon'],
                'aircraft'  => $bay['AC'],
                'pax_type'  => $bay['Type'] ?? null,
                'operators' => $bay['Operator'] ?? null,
                'priority'  => $bay['Priority'] ?? 5,
                'check_exist' => 1,
                'terminal'  => $bay['Terminal'] ?? null,
            ]);
        }
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
