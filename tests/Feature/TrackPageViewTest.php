<?php

namespace Tests\Feature;

use App\Models\Airports;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers TrackPageView: the homepage, live map, airport ladder and dashboard each record a
 * PageView, deduped to one per visitor per 15 minutes via a cookie, regardless of login state.
 */
class TrackPageViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_the_homepage_records_a_page_view(): void
    {
        $this->get(route('home'))->assertOk();

        $this->assertSame(1, PageView::where('page', 'home')->count());

        $log = PageView::where('page', 'home')->first();
        $this->assertNull($log->user_id);
        $this->assertNotEmpty($log->visitor_id);
    }

    public function test_visiting_the_live_map_records_a_page_view(): void
    {
        $this->get(route('mapIndex'))->assertOk();

        $this->assertSame(1, PageView::where('page', 'map')->count());
    }

    public function test_visiting_the_airport_ladder_records_a_page_view(): void
    {
        Airports::create(['icao' => 'YBBN', 'lat' => '-27.3842', 'lon' => '153.1175', 'name' => 'Brisbane', 'color' => '#fff', 'check_exist' => 1, 'status' => 'active']);

        $this->get(route('airportLadder', 'YBBN'))->assertOk();

        $this->assertSame(1, PageView::where('page', 'airport_ladder')->count());
    }

    public function test_visiting_the_dashboard_records_a_page_view(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Pilot']);

        DB::table('users')->insert([
            'id' => 888, 'fname' => 'Test', 'lname' => 'Pilot', 'email' => 'pilot888@example.com',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::find(888))->get(route('dashboard.index'))->assertOk();

        $this->assertSame(1, PageView::where('page', 'dashboard')->count());
        $this->assertSame(888, PageView::where('page', 'dashboard')->value('user_id'));
    }

    public function test_revisiting_within_15_minutes_does_not_double_count(): void
    {
        $first = $this->get(route('home'))->assertOk();

        $visitorCookie = $first->getCookie('ozbays_visitor');
        $dedupeCookie = $first->getCookie('ozbays_pv_seen_home');

        $this->assertNotNull($visitorCookie);
        $this->assertNotNull($dedupeCookie);

        $this->withCookie('ozbays_visitor', $visitorCookie->getValue())
            ->withCookie('ozbays_pv_seen_home', $dedupeCookie->getValue())
            ->get(route('home'))
            ->assertOk();

        $this->assertSame(1, PageView::where('page', 'home')->count());
    }

    public function test_revisiting_after_the_dedupe_cookie_expires_counts_again(): void
    {
        $first = $this->get(route('home'))->assertOk();
        $visitorCookie = $first->getCookie('ozbays_visitor');

        // No dedupe cookie sent this time - simulates it having expired.
        $this->withCookie('ozbays_visitor', $visitorCookie->getValue())
            ->get(route('home'))
            ->assertOk();

        $this->assertSame(2, PageView::where('page', 'home')->count());
    }

    public function test_visiting_a_different_tracked_page_counts_separately_in_the_same_window(): void
    {
        $first = $this->get(route('home'))->assertOk();
        $visitorCookie = $first->getCookie('ozbays_visitor');

        // Same visitor, different page - the home dedupe cookie shouldn't block the map visit.
        $this->withCookie('ozbays_visitor', $visitorCookie->getValue())
            ->get(route('mapIndex'))
            ->assertOk();

        $this->assertSame(1, PageView::where('page', 'home')->count());
        $this->assertSame(1, PageView::where('page', 'map')->count());
        $this->assertSame(
            PageView::where('page', 'home')->value('visitor_id'),
            PageView::where('page', 'map')->value('visitor_id')
        );
    }

    public function test_logged_in_visit_records_the_users_cid(): void
    {
        DB::table('users')->insert([
            'id' => 777, 'fname' => 'Test', 'lname' => 'Pilot', 'email' => 'pilot777@example.com',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find(777);

        $this->actingAs($user)->get(route('home'))->assertOk();

        $this->assertSame(777, PageView::where('page', 'home')->value('user_id'));
    }
}
