<?php

namespace Tests\Feature;

use App\Models\Airports;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AirportStatusRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = [
        'dashboard.admin.airport.disable',
        'dashboard.admin.airport.activate',
        'dashboard.admin.airport.live-disable',
        'dashboard.admin.airport.live-activate',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Pilot']);

        Airports::create([
            'icao' => 'YAAA', 'name' => 'Test', 'lat' => 0, 'lon' => 0, 'color' => 'purple',
            'eibt_variable' => 1.4, 'taxi_time' => 15, 'status' => 'testing', 'live_bays' => 0,
        ]);
    }

    private function createUser(int $id, string $roleName): User
    {
        DB::table('users')->insert([
            'id' => $id, 'fname' => 'Test', 'lname' => "User{$id}", 'email' => "user{$id}@example.com",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::find($id);
        $user->assignRole($roleName);

        return $user;
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (self::ROUTES as $route) {
            $this->post(route($route), ['icao' => 'YAAA'])->assertRedirect();
        }

        $this->assertSame('testing', Airports::where('icao', 'YAAA')->value('status'));
        $this->assertEquals(0, Airports::where('icao', 'YAAA')->value('live_bays'));
    }

    public function test_users_without_update_status_are_forbidden(): void
    {
        $user = $this->createUser(1, 'Pilot');

        foreach (self::ROUTES as $route) {
            $this->actingAs($user)->post(route($route), ['icao' => 'YAAA'])->assertForbidden();
        }

        $this->assertSame('testing', Airports::where('icao', 'YAAA')->value('status'));
        $this->assertEquals(0, Airports::where('icao', 'YAAA')->value('live_bays'));
    }

    public function test_users_with_update_status_can_change_the_airport(): void
    {
        $user = $this->createUser(1, 'Lead Developer');

        $this->actingAs($user)->post(route('dashboard.admin.airport.activate'), ['icao' => 'YAAA'])->assertRedirect();
        $this->actingAs($user)->post(route('dashboard.admin.airport.live-activate'), ['icao' => 'YAAA'])->assertRedirect();

        $this->assertSame('active', Airports::where('icao', 'YAAA')->value('status'));
        $this->assertEquals(1, Airports::where('icao', 'YAAA')->value('live_bays'));
    }
}
