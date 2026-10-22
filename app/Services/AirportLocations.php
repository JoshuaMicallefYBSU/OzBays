<?php

namespace App\Services;

use App\Models\AirportLocation;
use App\Models\Airports;

/**
 * Coordinates for any airport by ICAO code. OzBays airports come from the airports table, everything else is
 * looked up from AirLabs the first time it's needed and stored in airport_locations.
 */
class AirportLocations
{
    // An airport AirLabs didn't know (or a failed request) is retried after this long
    private const RETRY_AFTER_HOURS = 24;

    private array $memo = [];

    public function __construct(private AirLabsClient $airLabs)
    {
        //
    }

    /**
     * ['lat' => float, 'lon' => float], or null if unknown. With $fetch false only stored data is used,
     * so page views never wait on AirLabs.
     */
    public function coordinates(?string $icao, bool $fetch = true): ?array
    {
        $icao = strtoupper(trim((string) $icao));

        if (! preg_match('/^[A-Z0-9]{4}$/', $icao)) {
            return null;
        }

        if (array_key_exists($icao, $this->memo)) {
            return $this->memo[$icao];
        }

        $ozbays = Airports::where('icao', $icao)->first(['lat', 'lon']);
        if ($ozbays !== null && $ozbays->lat !== null && $ozbays->lon !== null) {
            return $this->memo[$icao] = ['lat' => (float) $ozbays->lat, 'lon' => (float) $ozbays->lon];
        }

        $stored = AirportLocation::where('icao', $icao)->first();
        if ($stored !== null && $stored->lat !== null) {
            return $this->memo[$icao] = ['lat' => (float) $stored->lat, 'lon' => (float) $stored->lon];
        }

        $recentlyTried = $stored !== null && $stored->updated_at->gt(now()->subHours(self::RETRY_AFTER_HOURS));
        if (! $fetch || $recentlyTried) {
            return null; // not memoised, so a later fetch in the same run can still look it up
        }

        $airport = $this->airLabs->getAirport($icao);

        // Record misses too (lat/lon null) so an unknown ICAO isn't requested on every run
        $location = AirportLocation::updateOrCreate(['icao' => $icao], [
            'name' => $airport['name'] ?? null,
            'lat'  => $airport['lat'] ?? null,
            'lon'  => $airport['lon'] ?? null,
        ]);
        $location->touch();

        return $this->memo[$icao] = $location->lat === null ? null : ['lat' => (float) $location->lat, 'lon' => (float) $location->lon];
    }
}
