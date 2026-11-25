<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Airports;
use App\Models\BayRequest;
use App\Models\Bays;
use App\Models\Flights;
use Illuminate\Support\Collection;

/**
 * The bays a pilot can request on their dashboard for the flight they are flying.
 *
 * Only bays the aircraft fits are ever offered. Each one is flagged "recommended" when the automatic
 * allocation (BayAllocation::buildBayQuery, strict level) would consider it - the operator is allowed
 * (their own, open, or "*") and the pax type matches - and the list is ranked the same way the
 * allocator ranks its candidates: operator, then bay priority, then how close the bay size is.
 */
class BayRequestOptions
{
    // Flight statuses where the aircraft is already on the ground - too late to request a bay
    private const ON_GROUND_STATUSES = ['Arrived', 'Taxiing', 'On Ground'];

    public function __construct(private AircraftSizes $sizes)
    {
    }

    // Can this flight request bays? Only flights filed into an OzBays airport
    public function airportFor(Flights $flight): ?Airports
    {
        if ($flight->callsign === null || $flight->arr === null) {
            return null;
        }

        return Airports::where('icao', $flight->arr)->first();
    }

    // Why requests can no longer be changed, or null while they still can
    public function lockReason(Flights $flight): ?string
    {
        if ($flight->scheduled_bay !== null) {
            return 'OzBays has assigned your bay - requests can no longer be changed.';
        }

        if (in_array($flight->status, self::ON_GROUND_STATUSES, true)) {
            return 'You are on the ground at '.$flight->arr.' - requests can no longer be changed.';
        }

        return null;
    }

    // Every bay at the arrival airport the aircraft fits, best match first
    public function options(Flights $flight): Collection
    {
        $operator = strtoupper(substr((string) $flight->callsign, 0, 3));
        $isFreight = $this->sizes->isFreightOnly($flight->ac) || Airline::isFreightCallsign((string) $flight->callsign);
        $allowedTypes = $this->sizes->allowedTypes($flight->ac);

        // Size group offset for each allowed type (0 = the aircraft's own group) - as BayAllocation::bestMatchingBays
        $typeGroup = [];
        foreach ($this->sizes->allowedGroups($flight->ac) as $offset => $types) {
            foreach ($types as $type) {
                $typeGroup[strtoupper($type)] ??= $offset;
            }
        }

        return Bays::where('airport', $flight->arr)->get()
            ->filter(fn (Bays $bay) => AircraftSizes::bayFits($bay->aircraft, $allowedTypes))
            ->map(function (Bays $bay) use ($flight, $operator, $isFreight, $typeGroup) {
                $operators = $bay->operators === null ? null : array_values(array_filter(array_map('trim', explode(',', $bay->operators)), 'strlen'));

                // 0 = the airline's own bay, 1 = open to everyone, 2 = another airline's bay open via "*", 3 = not open to this airline
                if ($operators === null || $operators === []) {
                    $operatorRank = 1;
                } elseif (in_array($operator, $operators, true)) {
                    $operatorRank = 0;
                } elseif (in_array('*', $operators, true)) {
                    $operatorRank = 2;
                } else {
                    $operatorRank = 3;
                }

                $paxMatches = $isFreight
                    ? $bay->pax_type === 'FRT'
                    : ($bay->pax_type === null || ($bay->pax_type !== 'FRT' && $bay->pax_type === $flight->type));

                $sizes = [];
                foreach (explode('/', (string) $bay->aircraft) as $type) {
                    $type = strtoupper(trim($type));
                    if (isset($typeGroup[$type])) {
                        $sizes[] = $typeGroup[$type];
                    }
                }

                $recommended = $operatorRank < 3 && $paxMatches;

                return [
                    'id' => $bay->id,
                    'bay' => $bay->bay,
                    'name' => $bay->display_name,
                    'terminal' => $bay->terminal,
                    'pax_type' => $bay->pax_type,
                    'operators' => $operators,
                    'max_size' => $this->sizes->largestType($bay->aircraft),
                    'aircraft' => $bay->aircraft,
                    // For the map picker - null when the bay has no usable position
                    'lat' => is_numeric($bay->lat) ? (float) $bay->lat : null,
                    'lon' => is_numeric($bay->lon) ? (float) $bay->lon : null,
                    'recommended' => $recommended,
                    'group' => $recommended ? ['own', 'open', 'shared'][$operatorRank] : 'other',
                    ...$this->status($bay, $flight),
                    '_rank' => [$recommended ? 0 : 1, $operatorRank, (int) $bay->priority, $sizes ? min($sizes) : PHP_INT_MAX],
                ];
            })
            ->sort(function ($a, $b) {
                return ($a['_rank'] <=> $b['_rank']) ?: strnatcasecmp($a['bay'], $b['bay']);
            })
            ->map(function ($option) {
                unset($option['_rank']);

                return $option;
            })
            ->values();
    }

    // Live status of a bay from the point of view of this flight
    public function status(Bays $bay, Flights $flight): array
    {
        if ($bay->status === 2) {
            return ['status' => 'occupied', 'status_label' => 'Occupied'];
        }

        if ($bay->status === 1 || $bay->callsign !== null) {
            return $bay->callsign === $flight->callsign
                ? ['status' => 'assigned', 'status_label' => 'Assigned to you']
                : ['status' => 'reserved', 'status_label' => 'Reserved for another aircraft'];
        }

        return ['status' => 'available', 'status_label' => 'Available'];
    }

    // The bay ids this flight has requested, first choice first
    public function requestedIds(Flights $flight): array
    {
        return BayRequest::forFlight($flight)->pluck('bay_id')->map(fn ($id) => (int) $id)->all();
    }
}
