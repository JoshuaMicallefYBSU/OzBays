<?php

namespace App\Http\Controllers;

use App\Models\BayRequest;
use App\Models\Flights;
use App\Services\BayRequestOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard "Request a Bay" section - a pilot flying into an OzBays airport picks up to 3 bays, in order.
 * BayAllocation tries them (first choice first) ahead of the real-world bay when it assigns the flight.
 */
class BayRequestController extends Controller
{
    public function __construct(private BayRequestOptions $options)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->state($request->user()->isFlying));
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bays' => 'present|array|max:'.BayRequest::MAX_REQUESTS,
            'bays.*' => 'integer|distinct',
        ]);

        $flight = $request->user()->isFlying;

        if ($this->options->airportFor($flight) === null) {
            return response()->json(['message' => 'No flight into an OzBays airport was found for your account.'] + $this->state($flight), 422);
        }

        if (($reason = $this->options->lockReason($flight)) !== null) {
            return response()->json(['message' => $reason] + $this->state($flight), 422);
        }

        // Only bays at the arrival airport the aircraft actually fits on
        $allowed = $this->options->options($flight)->pluck('id')->all();
        $bayIds = array_map('intval', $data['bays']);
        if (array_diff($bayIds, $allowed) !== []) {
            return response()->json(['message' => 'One or more of those bays can\'t be requested for this flight.'] + $this->state($flight), 422);
        }

        DB::transaction(function () use ($flight, $bayIds) {
            // A pilot only flies one flight at a time - anything left from an earlier flight goes too
            BayRequest::where('user_id', (int) $flight->cid)->delete();

            foreach ($bayIds as $index => $bayId) {
                BayRequest::create([
                    'user_id' => (int) $flight->cid,
                    'callsign' => $flight->callsign,
                    'airport' => $flight->arr,
                    'bay_id' => $bayId,
                    'position' => $index + 1,
                ]);
            }
        });

        return response()->json($this->state($flight));
    }

    private function state(Flights $flight): array
    {
        $airport = $this->options->airportFor($flight);

        if ($airport === null) {
            return ['available' => false, 'max' => BayRequest::MAX_REQUESTS];
        }

        $options = $this->options->options($flight);
        $requested = $this->options->requestedIds($flight);
        $assigned = $flight->scheduled_bay !== null ? $flight->mapBay : null;

        return [
            'available' => true,
            'max' => BayRequest::MAX_REQUESTS,
            'flight' => [
                'callsign' => $flight->callsign,
                'aircraft' => $flight->ac,
                'arrival' => $flight->arr,
                'airport_name' => $airport->name,
                'airport_lat' => is_numeric($airport->lat) ? (float) $airport->lat : null,
                'airport_lon' => is_numeric($airport->lon) ? (float) $airport->lon : null,
                'operator' => strtoupper(substr((string) $flight->callsign, 0, 3)),
                'type' => $flight->type,
            ],
            'locked' => ($reason = $this->options->lockReason($flight)) !== null,
            'lock_reason' => $reason,
            'assigned_bay' => $assigned ? [
                'id' => $assigned->id,
                'name' => $assigned->display_name,
                'requested_position' => ($position = array_search($assigned->id, $requested, true)) === false ? null : $position + 1,
            ] : null,
            // Requested bays that are still on offer (a bay removed by an airport update drops out)
            'requests' => array_values(array_intersect($requested, $options->pluck('id')->all())),
            'options' => $options,
        ];
    }
}
