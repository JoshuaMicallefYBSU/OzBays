<?php

use App\Models\FlightLogs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flight logs now record where the flight came from, the bay OzBays assigned and the bay the pilot actually
     * parked at. Flights get the furthest distance seen from the arrival airport, used for the dashboard progress bar.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('flight_logs', function (Blueprint $table) {
            $table->string('departure')->nullable()->after('airline');
            $table->string('assigned_bay')->nullable()->after('aircraft');
            $table->string('parked_bay')->nullable()->after('assigned_bay');
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->integer('route_distance')->nullable()->after('distance');
        });

        // Older logs took the first letters of any callsign as the airline (e.g. N26KR -> "N"). Re-derive them.
        DB::table('flight_logs')->select(['id', 'callsign', 'airline'])->orderBy('id')->chunkById(500, function ($logs) {
            foreach ($logs as $log) {
                $airline = FlightLogs::airlineFromCallsign($log->callsign);

                if ($airline !== $log->airline) {
                    DB::table('flight_logs')->where('id', $log->id)->update(['airline' => $airline]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('flight_logs', function (Blueprint $table) {
            $table->dropColumn(['departure', 'assigned_bay', 'parked_bay']);
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn(['route_distance']);
        });
    }
};
