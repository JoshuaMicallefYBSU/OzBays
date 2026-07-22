<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\UserPreference;
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

    public function test_uplink_is_built_for_a_user_with_no_preferences_row(): void
    {
        $uplink = $this->invokeHoppieFunction(999);

        $this->assertIsString($uplink);
        $this->assertStringContainsString('YBBN ARRIVAL INFO', $uplink);
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

        $this->assertIsString($uplink);
        $this->assertStringContainsString('YBBN ARRIVAL INFO', $uplink);
    }

    public function test_uplink_is_cancelled_for_a_user_who_disabled_hoppie(): void
    {
        $this->createUserWithId(1000002);
        UserPreference::create(['user_id' => 1000002, 'hoppie_usage' => 0]);

        $uplink = $this->invokeHoppieFunction(1000002);

        $this->assertNull($uplink);
    }
}
