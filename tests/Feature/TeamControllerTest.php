<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 'Lead Developer'/'Developer' come from the role-rename migration, but
        // 'Maintainer'/'Contributor'/'Pilot' only exist via DatabaseSeeder (which can't run
        // against sqlite as-is), so a migrated-but-unseeded test DB doesn't have them yet.
        foreach (['Maintainer', 'Contributor', 'Pilot'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
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

    public function test_team_page_groups_members_into_leadership_and_community(): void
    {
        $leadDeveloper = $this->createUser(1, 'Lead Developer');
        $developer = $this->createUser(2, 'Developer');
        $maintainer = $this->createUser(3, 'Maintainer');
        $contributor = $this->createUser(4, 'Contributor');
        $this->createUser(5, 'Pilot'); // should not appear anywhere on the page

        $response = $this->get(route('team.index'));

        $response->assertStatus(200);
        $response->assertViewHas('leadership', function ($leadership) use ($leadDeveloper, $developer) {
            return $leadership->pluck('id')->sort()->values()->all() === collect([$leadDeveloper->id, $developer->id])->sort()->values()->all();
        });
        $response->assertViewHas('community', function ($community) use ($maintainer, $contributor) {
            return $community->pluck('id')->sort()->values()->all() === collect([$maintainer->id, $contributor->id])->sort()->values()->all();
        });
    }

    public function test_team_page_renders_without_any_members(): void
    {
        $response = $this->get(route('team.index'));

        $response->assertStatus(200);
        $response->assertSee('No leadership team members yet.');
        $response->assertSee('No community team members yet.');
    }
}
