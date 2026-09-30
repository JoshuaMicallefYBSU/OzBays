<?php

namespace Tests\Feature;

use App\Models\ContributorApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContributorApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Maintainer/Contributor/Pilot only exist via DatabaseSeeder, which doesn't run against sqlite
        foreach (['Maintainer', 'Contributor', 'Pilot'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
        Role::findByName('Maintainer')->givePermissionTo(Permission::firstOrCreate(['name' => 'review applications']));
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

    public function test_pilot_can_submit_an_application(): void
    {
        $user = $this->createUser(1, 'Pilot');

        $this->actingAs($user)->post(route('dashboard.contribute.store'), [
            'description' => 'Keep YMML bays up to date',
            'experience'  => 'Ramp agent for 3 years',
        ])->assertRedirect(route('dashboard.index'));

        $application = ContributorApplication::first();
        $this->assertSame($user->id, $application->user_id);
        $this->assertSame('pending', $application->status);
        $this->assertSame('Ramp agent for 3 years', $application->experience);
    }

    public function test_description_is_required(): void
    {
        $user = $this->createUser(1, 'Pilot');

        $this->actingAs($user)->post(route('dashboard.contribute.store'), ['experience' => 'x'])
            ->assertSessionHasErrors('description');

        $this->assertSame(0, ContributorApplication::count());
    }

    public function test_cannot_apply_twice_while_pending_or_when_already_on_the_team(): void
    {
        $pilot = $this->createUser(1, 'Pilot');
        ContributorApplication::create(['user_id' => $pilot->id, 'description' => 'first']);

        $this->actingAs($pilot)->post(route('dashboard.contribute.store'), ['description' => 'second']);

        $contributor = $this->createUser(2, 'Contributor');
        $this->actingAs($contributor)->post(route('dashboard.contribute.store'), ['description' => 'again']);

        $this->assertSame(1, ContributorApplication::count());
    }

    public function test_reviewer_sees_outstanding_applications_and_can_approve(): void
    {
        $pilot = $this->createUser(1, 'Pilot');
        $reviewer = $this->createUser(2, 'Maintainer');
        $application = ContributorApplication::create(['user_id' => $pilot->id, 'description' => 'Perth data']);

        $this->actingAs($reviewer)->get(route('dashboard.admin.applications.index'))
            ->assertOk()
            ->assertSee('Perth data');

        $this->actingAs($reviewer)->post(route('dashboard.admin.applications.approve', $application))->assertRedirect();

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame($reviewer->id, $application->reviewed_by);
        $this->assertTrue($pilot->fresh()->hasRole('Contributor'));
    }

    public function test_reviewer_can_reject_and_the_user_can_apply_again(): void
    {
        $pilot = $this->createUser(1, 'Pilot');
        $reviewer = $this->createUser(2, 'Maintainer');
        $application = ContributorApplication::create(['user_id' => $pilot->id, 'description' => 'first']);

        $this->actingAs($reviewer)->post(route('dashboard.admin.applications.reject', $application));

        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertFalse($pilot->fresh()->hasRole('Contributor'));

        $this->actingAs($pilot)->post(route('dashboard.contribute.store'), ['description' => 'second']);
        $this->assertSame(1, ContributorApplication::pending()->count());
    }

    public function test_dashboard_shows_apply_button_and_reviewers_see_the_outstanding_badge(): void
    {
        $pilot = $this->createUser(1, 'Pilot');
        $reviewer = $this->createUser(2, 'Maintainer');
        Role::findByName('Maintainer')->givePermissionTo(Permission::firstOrCreate(['name' => 'view data']));

        $this->actingAs($pilot)->get(route('dashboard.index'))->assertOk()->assertSee('Apply to Contribute');

        ContributorApplication::create(['user_id' => $pilot->id, 'description' => 'x']);

        // Button hidden while pending
        $this->actingAs($pilot)->get(route('dashboard.index'))->assertOk()->assertDontSee('Apply to Contribute');

        $this->actingAs($reviewer)->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('Contributor Applications')
            ->assertSee('badge-warning', false);
    }

    public function test_non_reviewers_cannot_access_the_admin_page(): void
    {
        $pilot = $this->createUser(1, 'Pilot');
        $application = ContributorApplication::create(['user_id' => $pilot->id, 'description' => 'x']);

        $this->actingAs($pilot)->get(route('dashboard.admin.applications.index'))->assertForbidden();
        $this->actingAs($pilot)->post(route('dashboard.admin.applications.approve', $application))->assertForbidden();

        $this->assertFalse($pilot->fresh()->hasRole('Contributor'));
    }
}
