<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the Developer-only "Page Access" dashboard: that it's properly gated, and that
 * the 24h/7d/30d summary counts and daily chart data reflect what's actually been logged.
 */
class PageAccessControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Pilot']);
        Role::firstOrCreate(['name' => 'Developer']);
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

    public function test_pilots_are_forbidden_from_the_page_access_dashboard(): void
    {
        $pilot = $this->createUser(1, 'Pilot');

        $this->actingAs($pilot)->get(route('dashboard.pageAccess.index'))->assertForbidden();
    }

    public function test_developers_can_view_the_page_access_dashboard(): void
    {
        $developer = $this->createUser(2, 'Developer');

        PageView::create(['page' => 'home', 'visitor_id' => 'v1', 'user_id' => null]);
        PageView::create(['page' => 'home', 'visitor_id' => 'v2', 'user_id' => null]);
        PageView::create(['page' => 'map', 'visitor_id' => 'v1', 'user_id' => null]);

        $this->actingAs($developer)->get(route('dashboard.pageAccess.index'))
            ->assertOk()
            ->assertSee('Homepage')
            ->assertSee('Live Map')
            ->assertSee('Airport Ladder')
            ->assertSee('Dashboard');
    }

    // created_at isn't mass-assignable (it's DB-defaulted via useCurrent() for real traffic),
    // so backdating a row for these time-window tests goes through forceFill() instead.
    private function createPageViewAt(string $page, string $visitorId, \Illuminate\Support\Carbon $createdAt): PageView
    {
        $pageView = new PageView(['page' => $page, 'visitor_id' => $visitorId]);
        $pageView->forceFill(['created_at' => $createdAt])->save();

        return $pageView;
    }

    public function test_summary_counts_respect_the_time_window_boundaries(): void
    {
        $developer = $this->createUser(3, 'Developer');

        // Inside all three windows
        $this->createPageViewAt('home', 'recent', now()->subHours(2));
        // Inside 7d/30d, but outside 24h
        $this->createPageViewAt('home', 'threeDaysAgo', now()->subDays(3));
        // Inside 30d only
        $this->createPageViewAt('home', 'twoWeeksAgo', now()->subDays(14));
        // Outside all three windows
        $this->createPageViewAt('home', 'ancient', now()->subDays(45));

        $response = $this->actingAs($developer)->get(route('dashboard.pageAccess.index'))->assertOk();

        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['24h']['home']);
        $this->assertSame(2, $summary['7d']['home']);
        $this->assertSame(3, $summary['30d']['home']);
    }

    public function test_daily_series_is_zero_filled_for_the_last_30_days(): void
    {
        $developer = $this->createUser(4, 'Developer');

        $this->createPageViewAt('home', 'v1', now()->subDays(5));
        $this->createPageViewAt('home', 'v2', now()->subDays(5));

        $response = $this->actingAs($developer)->get(route('dashboard.pageAccess.index'))->assertOk();

        $daily = $response->viewData('daily');
        $this->assertCount(30, $daily['home']);
        $this->assertSame(2, $daily['home'][now()->subDays(5)->toDateString()]);
        $this->assertSame(0, $daily['home'][now()->toDateString()]);
    }
}
