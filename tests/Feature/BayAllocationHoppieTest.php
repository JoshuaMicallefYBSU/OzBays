<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airports;
use App\Models\UserPreference;
use App\Services\HoppieClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HoppieFunction() used to bail out (return null) for ANY user with a
 * user_preferences row, regardless of whether they'd actually disabled
 * Hoppie messaging — these pin the corrected behaviour: only an explicit
 * hoppie_usage = 0 should cancel the uplink.
 */
class BayAllocationHoppieTest extends TestCase
{
    use RefreshDatabase;

    private function invokeHoppieFunction(int $cid)
    {
        $job = new BayAllocation;
        $ref = new \ReflectionClass($job);
        $method = $ref->getMethod('HoppieFunction');
        $method->setAccessible(true);

        return $method->invoke($job, 1, 'QFA123', $cid, 'YSSY', 'YBBN', 'DOM', 'A1', null);
    }

    // Outside production the message is never actually sent, so HoppieFunction() returns the
    // "not connected" placeholder rather than building the full CPDLC text - the point of this
    // test is that it's a string (the function proceeded past the preference check), not null
    // (which is what the bailout bug produced).
    public function test_uplink_is_built_for_a_user_with_no_preferences_row(): void
    {
        $uplink = $this->invokeHoppieFunction(999);

        $this->assertSame('Not connected to Hoppie', $uplink);
    }

    /**
     * Inserted directly via the query builder — going through User::create() fires a
     * created() hook that assumes an autoincrement id, which this custom (VATSIM-cid-keyed,
     * non-autoincrement) users table doesn't have. That's a separate, unrelated bug outside
     * the scope of this fix, so we sidestep it here rather than depending on it.
     */
    private function createUserWithId(int $id): void
    {
        DB::table('users')->insert([
            'id' => $id, 'fname' => 'Test', 'lname' => 'Pilot', 'email' => "pilot{$id}@example.com",
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_uplink_is_built_for_a_user_who_has_left_hoppie_enabled(): void
    {
        $this->createUserWithId(1000001);
        UserPreference::create(['user_id' => 1000001, 'hoppie_usage' => 1]);

        $uplink = $this->invokeHoppieFunction(1000001);

        $this->assertSame('Not connected to Hoppie', $uplink);
    }

    private function arrBayLine(?string $bayType, string $arrBay): string
    {
        $method = (new \ReflectionClass(BayAllocation::class))->getMethod('BuildCPDLCMessage');
        $method->setAccessible(true);

        $uplink = $method->invoke(new BayAllocation, 1, 'QFA123', 'YSSY', 'YPPH', $bayType, $arrBay, 1);
        preg_match('/ASSIGNED BAY: (.*)$/m', $uplink, $m);

        return $m[1];
    }

    public function test_arr_bay_shows_the_bay_type_when_the_bay_has_one(): void
    {
        $this->assertSame('INTL, I8', $this->arrBayLine('INTL', 'I8'));
        $this->assertSame('DOM, 10', $this->arrBayLine('DOM', '10'));
    }

    public function test_arr_bay_shows_only_the_name_when_the_bay_has_no_type(): void
    {
        $this->assertSame('Maroomba 2 (MB2)', $this->arrBayLine(null, 'Maroomba 2 (MB2)'));
        $this->assertSame('National Jet 6R (NJ6R)', $this->arrBayLine(null, 'National Jet 6R (NJ6R)'));
    }

    public function test_uplink_is_cancelled_for_a_user_who_disabled_hoppie(): void
    {
        $this->createUserWithId(1000002);
        UserPreference::create(['user_id' => 1000002, 'hoppie_usage' => 0]);

        $uplink = $this->invokeHoppieFunction(1000002);

        $this->assertNull($uplink);
    }

    /**
     * The CPDLC text is only worth building when it's actually going out on the wire - these
     * pin that: a real message is built and sent when the pilot is connected and allowed to
     * receive it, but nothing more than the generic placeholder is built when it isn't.
     */
    public function test_uplink_is_built_and_sent_when_connected_and_allowed(): void
    {
        Airports::create(['icao' => 'YBBN', 'lat' => '-27.3842', 'lon' => '153.1175', 'name' => 'Brisbane', 'color' => '#fff', 'check_exist' => 1, 'status' => 'active']);

        $this->mock(HoppieClient::class, function ($mock) {
            $mock->shouldReceive('isConnected')->once()->andReturn(true);
            $mock->shouldReceive('sendTelex')->once()->andReturn(true);
            $mock->shouldReceive('callsign')->andReturn('OZBAYS');
        });

        $uplink = $this->withProductionEnv(fn () => $this->invokeHoppieFunction(999));

        $this->assertStringContainsString('YBBN ARRIVAL INFO', $uplink);
    }

    public function test_uplink_stays_the_placeholder_when_blocked_by_testing_mode(): void
    {
        Airports::create(['icao' => 'YBBN', 'lat' => '-27.3842', 'lon' => '153.1175', 'name' => 'Brisbane', 'color' => '#fff', 'check_exist' => 1, 'status' => 'testing']);

        $this->mock(HoppieClient::class, function ($mock) {
            $mock->shouldReceive('isConnected')->once()->andReturn(true);
            $mock->shouldNotReceive('sendTelex');
        });

        // cid 999 is not in the tester list, so a connected pilot at a testing-mode airport
        // still shouldn't get a message - and the full CPDLC text should never be built for it.
        $uplink = $this->withProductionEnv(fn () => $this->invokeHoppieFunction(999));

        $this->assertSame('Not connected to Hoppie', $uplink);
    }

    private function withProductionEnv(callable $callback)
    {
        $previous = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';

        try {
            return $callback();
        } finally {
            putenv("APP_ENV={$previous}");
            $_ENV['APP_ENV'] = $previous;
        }
    }
}
