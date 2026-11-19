<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDOException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRolesAndOutagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Pilot']);
        Cache::store('file')->forget('db-outage:since');
        Cache::store('file')->forget('db-outage:alerted');
    }

    protected function tearDown(): void
    {
        Cache::store('file')->forget('db-outage:since');
        Cache::store('file')->forget('db-outage:alerted');

        parent::tearDown();
    }

    public function test_new_users_are_pilots_straight_away(): void
    {
        $user = User::create(['id' => 1234567, 'fname' => 'New', 'lname' => 'Pilot', 'email' => 'new@example.com']);

        $this->assertSame(['Pilot'], $user->fresh()->getRoleNames()->all());
    }

    public function test_existing_users_without_a_role_become_pilots(): void
    {
        DB::table('users')->insert(['id' => 1, 'fname' => 'No', 'lname' => 'Role', 'email' => 'a@example.com', 'created_at' => now(), 'updated_at' => now()]);
        $developer = User::create(['id' => 2, 'fname' => 'Dev', 'lname' => 'User', 'email' => 'b@example.com']);
        $developer->syncRoles([Role::firstOrCreate(['name' => 'Developer'])]);

        $migration = require database_path('migrations/2026_11_17_120000_give_role_less_users_pilot.php');
        $migration->up();

        $this->assertSame(['Pilot'], User::find(1)->getRoleNames()->all());
        $this->assertSame(['Developer'], User::find(2)->getRoleNames()->all()); // existing roles left alone
    }

    public function test_a_short_database_outage_is_not_alerted_but_a_long_one_is(): void
    {
        $lostConnection = new QueryException('mysql', 'select * from flights', [], new PDOException('SQLSTATE[HY000] [2002] No such file or directory'));
        $cache = Cache::store('file');

        // First failure - just remembered
        report($lostConnection);
        $this->assertTrue($cache->has('db-outage:since'));
        $this->assertFalse($cache->has('db-outage:alerted'));

        // Still down 5 minutes later - alerted once
        $cache->put('db-outage:since', now()->subMinutes(5)->getTimestamp(), now()->addMinutes(10));
        report($lostConnection);
        $this->assertTrue($cache->has('db-outage:alerted'));
    }

    public function test_other_errors_do_not_count_as_an_outage(): void
    {
        report(new \RuntimeException('Something else broke'));

        $this->assertFalse(Cache::store('file')->has('db-outage:since'));
    }
}
