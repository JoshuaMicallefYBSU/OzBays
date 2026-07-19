<?php

namespace Tests\Feature;

use App\Jobs\AerodromeUpdates;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pins the fixes from the 2026-07 security review: admin route protection,
 * the settings IDOR, OAuth state verification, and the AerodromeUpdates
 * wipe-on-malformed-JSON guard.
 */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function makeUser(int $id, array $permissions = []): User
    {
        $user = User::create(['id' => $id, 'fname' => 'Test', 'lname' => 'Pilot', 'email' => "user{$id}@example.com"]);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    public function test_admin_airport_routes_reject_guests(): void
    {
        $this->get('/admin/airport')->assertRedirect();
        $this->get('/admin/airport/YSSY')->assertRedirect();
        $this->get('/admin/aircraft')->assertRedirect();
        $this->post('/admin/airport/activate', ['icao' => 'YSSY'])->assertRedirect();
        $this->post('/admin/airport/disable', ['icao' => 'YSSY'])->assertRedirect();
        $this->get('/update/airports')->assertRedirect();
    }

    public function test_admin_airport_routes_reject_users_without_permission(): void
    {
        $user = $this->makeUser(100001);

        $this->actingAs($user)->get('/admin/airport')->assertForbidden();
        $this->actingAs($user)->post('/admin/airport/activate', ['icao' => 'YSSY'])->assertForbidden();
        $this->actingAs($user)->get('/update/airports')->assertForbidden();
    }

    public function test_airport_toggle_works_for_a_permitted_user(): void
    {
        Airports::create(['icao' => 'YSSY', 'lat' => '-33.9', 'lon' => '151.1', 'name' => 'Sydney', 'color' => '#fff', 'status' => 'testing', 'check_exist' => 1]);
        $user = $this->makeUser(100002, ['update status']);

        $this->actingAs($user)->post('/admin/airport/activate', ['icao' => 'YSSY'])->assertRedirect();

        $this->assertSame('active', Airports::where('icao', 'YSSY')->first()->status);
    }

    public function test_test_endpoint_is_unavailable_outside_local_environment(): void
    {
        // The suite runs with APP_ENV=testing, so the local-only gate must 404.
        $this->get('/test/vatsim-api')->assertNotFound();
    }

    public function test_settings_save_ignores_a_foreign_user_id_in_the_request(): void
    {
        $alice = $this->makeUser(100003);
        $bob = $this->makeUser(100004);
        $bob->getUserPreferencesOrCreate();

        $this->actingAs($alice)->post('/dashboard/my-settings/save', [
            'id' => $bob->id, // previously let Alice edit Bob's settings
            'name_format' => 3,
            'hoppie_usage' => 0,
            'email_feedback' => 0,
            'news_notifications' => 0,
        ])->assertRedirect();

        $this->assertSame(0, (int) $alice->fresh()->getUserPreferencesOrCreate()->hoppie_usage);
        $this->assertSame(1, (int) $bob->fresh()->getUserPreferencesOrCreate()->hoppie_usage);
    }

    public function test_sso_callback_rejects_a_mismatched_state(): void
    {
        $response = $this->withSession(['state' => 'expected-state'])
            ->get('/auth/connect/validate?state=wrong&code=abc');

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_sso_callback_rejects_a_missing_state(): void
    {
        $response = $this->get('/auth/connect/validate?code=abc');

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_user_creation_writes_preferences_for_the_correct_user_id(): void
    {
        $user = User::create(['id' => 1234567, 'fname' => 'A', 'lname' => 'B', 'email' => 'a@example.com']);

        $this->assertSame(1234567, $user->id);
        $this->assertDatabaseHas('user_preferences', ['user_id' => 1234567]);
        $this->assertDatabaseMissing('user_preferences', ['user_id' => 0]);
    }

    public function test_full_name_never_returns_blank_even_with_an_invalid_name_format(): void
    {
        $user = $this->makeUser(100005);

        $preferences = $user->getUserPreferencesOrCreate();
        $preferences->name_format = 99; // out of range - used to return null
        $preferences->save();

        $this->assertSame('Test P - 100005', $user->fresh()->fullName('FLC'));
        $this->assertSame('Test P', $user->fresh()->fullName('FL'));
        $this->assertSame('Test', $user->fresh()->fullName('F'));
    }

    public function test_full_name_formats_render_with_the_correct_cid(): void
    {
        $user = $this->makeUser(100006);

        $preferences = $user->getUserPreferencesOrCreate();
        $preferences->name_format = 3;
        $preferences->save();

        $this->assertSame('Test Pilot - 100006', $user->fresh()->fullName('FLC'));
    }

    public function test_aerodrome_updates_aborts_instead_of_wiping_on_malformed_json(): void
    {
        Airports::create(['icao' => 'YSSY', 'lat' => '-33.9', 'lon' => '151.1', 'name' => 'Sydney', 'color' => '#fff', 'status' => 'active', 'check_exist' => 1]);
        Bays::create(['airport' => 'YSSY', 'bay' => 'A1', 'lat' => '-33.9', 'lon' => '151.1', 'aircraft' => 'A321', 'priority' => 1, 'check_exist' => 1]);

        File::shouldReceive('get')->andReturn('this is not valid json');

        (new AerodromeUpdates)->handle();

        $this->assertSame(1, Airports::count());
        $this->assertSame(1, Bays::count());
    }
}
