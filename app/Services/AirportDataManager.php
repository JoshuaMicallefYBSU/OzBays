<?php

namespace App\Services;

use App\Models\Airports;
use App\Models\Bays;
use App\Models\DataChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AirportDataManager
{
    public function normaliseImport(array $document): array
    {
        $airports = $document['Airports'] ?? $document['airports'] ?? $document;

        if (! is_array($airports) || array_is_list($airports)) {
            throw ValidationException::withMessages(['file' => 'JSON must contain an Airports object keyed by ICAO code.']);
        }

        $normalised = [];
        foreach ($airports as $key => $airport) {
            if (! is_array($airport)) {
                throw ValidationException::withMessages(['file' => "Airport {$key} must be an object."]);
            }

            $icao = strtoupper((string) ($airport['icao'] ?? $key));
            $settings = $airport['settings'] ?? [];
            $parking = $airport['parking'] ?? $airport['bays'] ?? [];

            if (! preg_match('/^[A-Z0-9]{4}$/', $icao)) {
                throw ValidationException::withMessages(['file' => "{$icao} is not a valid four-character ICAO code."]);
            }
            foreach (['name', 'lat', 'lon'] as $field) {
                if (! array_key_exists($field, $airport)) {
                    throw ValidationException::withMessages(['file' => "{$icao} is missing {$field}."]);
                }
            }
            if (! is_numeric($airport['lat']) || ! is_numeric($airport['lon']) || ! is_array($parking)) {
                throw ValidationException::withMessages(['file' => "{$icao} has invalid coordinates or parking data."]);
            }

            $bays = [];
            foreach ($parking as $bayName => $bay) {
                if (! is_array($bay) || ! isset($bay['lat'], $bay['lon']) || ! is_numeric($bay['lat']) || ! is_numeric($bay['lon'])) {
                    throw ValidationException::withMessages(['file' => "{$icao} bay {$bayName} has invalid coordinates."]);
                }
                $bays[] = [
                    'bay' => (string) ($bay['bay'] ?? $bayName),
                    'lat' => $bay['lat'], 'lon' => $bay['lon'],
                    'aircraft' => $bay['AC'] ?? $bay['aircraft'] ?? null,
                    'operators' => $bay['Operator'] ?? $bay['operators'] ?? null,
                    'terminal' => $bay['Terminal'] ?? $bay['terminal'] ?? null,
                    'pax_type' => $bay['Type'] ?? $bay['pax_type'] ?? null,
                    'priority' => $bay['Priority'] ?? $bay['priority'] ?? null,
                ];
            }

            $normalised[] = [
                'airport' => [
                    'icao' => $icao, 'name' => (string) $airport['name'],
                    'lat' => $airport['lat'], 'lon' => $airport['lon'],
                    'color' => $settings['color'] ?? $airport['color'] ?? '#6c757d',
                    'eibt_variable' => $settings['eibt_config'] ?? $airport['eibt_variable'] ?? null,
                    'taxi_time' => $settings['taxi_time'] ?? $airport['taxi_time'] ?? null,
                    'status' => $settings['status'] ?? $airport['status'] ?? 'testing',
                    'live_type' => $settings['airport_type'] ?? $airport['live_type'] ?? null,
                    'live_update_times' => $settings['update_time_utc'] ?? $airport['live_update_times'] ?? null,
                ],
                'bays' => $bays,
            ];
        }

        return $normalised;
    }

    public function approve(DataChangeRequest $change): void
    {
        DB::transaction(function () use ($change) {
            $payload = $change->payload;
            $airportData = $payload['airport'];
            $airport = Airports::updateOrCreate(['icao' => $airportData['icao']], $airportData);
            $names = [];

            foreach ($payload['bays'] as $bayData) {
                $names[] = $bayData['bay'];
                Bays::updateOrCreate(
                    ['airport' => $airport->icao, 'bay' => $bayData['bay']],
                    array_merge($bayData, ['airport' => $airport->icao])
                );
            }

            $obsoleteBays = Bays::where('airport', $airport->icao);
            if ($names !== []) {
                $obsoleteBays->whereNotIn('bay', $names);
            }
            $obsoleteBays->delete();
        });
    }
}
