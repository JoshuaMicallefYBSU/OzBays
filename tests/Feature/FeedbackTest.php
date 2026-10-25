<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.test/api/webhooks/1/abc';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Pilot']);
        config(['services.discord.feedback_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response(null, 204)]);
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

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'ratings' => array_fill_keys(array_keys(Feedback::QUESTIONS), 8),
            'flight'  => 'QFA703 into YMML',
            'body'    => '<h3>Was your bay assignment appropriate?</h3><p>Got <strong>B21</strong>, spot on.</p>',
        ];
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/provide-feedback')->assertRedirect(route('auth.sso.login'));
        $this->post('/provide-feedback', $this->payload())->assertRedirect(route('auth.sso.login'));

        $this->assertSame(0, Feedback::count());
    }

    public function test_form_shows_the_sliders_and_lead_questions(): void
    {
        $this->actingAs($this->createUser(1, 'Pilot'))
            ->get(route('feedback.create'))
            ->assertOk()
            ->assertSee('Provide your Feedback!')
            ->assertSee('name="ratings[enjoyment]"', false)
            ->assertSee('Was your bay assignment appropriate for your aircraft and airline?')
            ->assertSee('Was the TELEX message received through Hoppie appropriate?');
    }

    public function test_feedback_is_saved_and_posted_to_the_webhook(): void
    {
        $user = $this->createUser(1234567, 'Pilot');

        $response = $this->actingAs($user)->post(route('feedback.store'), $this->payload());

        $feedback = Feedback::first();
        $response->assertRedirect(route('feedback.my.show', $feedback));
        $this->assertSame($user->id, $feedback->user_id);
        $this->assertSame(8, $feedback->ratings['enjoyment']);
        $this->assertSame('QFA703 into YMML', $feedback->flight);
        $this->assertStringContainsString('<strong>B21</strong>', $feedback->body);

        Http::assertSent(function (HttpRequest $request) use ($feedback) {
            $embed = $request['embeds'][0] ?? [];

            return $request->url() === self::WEBHOOK
                && $embed['url'] === route('dashboard.feedback.show', $feedback)
                && str_contains(json_encode($embed['fields']), '1234567')
                && str_contains($embed['description'], 'spot on');
        });
    }

    public function test_html_is_sanitised_before_saving(): void
    {
        $this->actingAs($this->createUser(1, 'Pilot'))->post(route('feedback.store'), $this->payload([
            'body' => '<p onclick="steal()">Hello<script>alert(1)</script></p><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a>',
        ]));

        $body = Feedback::first()->body;
        $this->assertStringContainsString('Hello', $body);
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('javascript:', $body);
    }

    public function test_ratings_must_be_1_to_10_and_written_feedback_is_required(): void
    {
        $user = $this->createUser(1, 'Pilot');

        $this->actingAs($user)->post(route('feedback.store'), $this->payload([
            'ratings' => ['enjoyment' => 11] + array_fill_keys(array_keys(Feedback::QUESTIONS), 5),
        ]))->assertSessionHasErrors('ratings.enjoyment');

        $this->actingAs($user)->post(route('feedback.store'), $this->payload(['body' => '<p><script>x</script></p>']))
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Feedback::count());
        Http::assertNothingSent();
    }

    public function test_feedback_is_kept_when_the_webhook_fails(): void
    {
        Http::fake([self::WEBHOOK => Http::response('nope', 500)]);

        $this->actingAs($this->createUser(1, 'Pilot'))
            ->post(route('feedback.store'), $this->payload())
            ->assertRedirect(route('feedback.my.show', 1));

        $this->assertSame(1, Feedback::count());
    }

    public function test_developers_can_view_feedback_and_pilots_cannot(): void
    {
        $pilot = $this->createUser(1, 'Pilot');
        $this->actingAs($pilot)->post(route('feedback.store'), $this->payload());
        $feedback = Feedback::first();

        $this->actingAs($pilot)->get(route('dashboard.feedback.index'))->assertForbidden();
        $this->actingAs($pilot)->get(route('dashboard.feedback.show', $feedback))->assertForbidden();

        $developer = $this->createUser(2, 'Developer');
        $this->actingAs($developer)->get(route('dashboard.feedback.index'))->assertOk()->assertSee('QFA703 into YMML');
        $this->actingAs($developer)->get(route('dashboard.feedback.show', $feedback))->assertOk()->assertSee('spot on')->assertSee('8/10');
    }

    public function test_submitter_can_view_and_reply_to_their_own_feedback(): void
    {
        $pilot = $this->createUser(1, 'Pilot');
        $this->actingAs($pilot)->post(route('feedback.store'), $this->payload());
        $feedback = Feedback::first();

        $this->actingAs($pilot)->get(route('feedback.my.index'))->assertOk()->assertSee('QFA703 into YMML');

        $this->actingAs($pilot)
            ->post(route('feedback.my.reply', $feedback), ['body' => 'Following up on this.'])
            ->assertRedirect(route('feedback.my.show', $feedback));

        $this->actingAs($pilot)->get(route('feedback.my.show', $feedback))->assertOk()->assertSee('Following up on this.');
        $this->assertSame(1, $feedback->replies()->count());
        $this->assertSame($pilot->id, $feedback->replies()->first()->user_id);
    }

    public function test_a_user_cannot_view_or_reply_to_someone_elses_feedback(): void
    {
        $owner = $this->createUser(1, 'Pilot');
        $this->actingAs($owner)->post(route('feedback.store'), $this->payload());
        $feedback = Feedback::first();

        $otherUser = $this->createUser(2, 'Pilot');
        $this->actingAs($otherUser)->get(route('feedback.my.show', $feedback))->assertForbidden();
        $this->actingAs($otherUser)
            ->post(route('feedback.my.reply', $feedback), ['body' => 'Not mine to reply to.'])
            ->assertForbidden();

        $this->assertSame(0, $feedback->replies()->count());
    }

    public function test_developer_reply_notifies_the_submitter(): void
    {
        Notification::fake();

        $pilot = $this->createUser(1, 'Pilot');
        $this->actingAs($pilot)->post(route('feedback.store'), $this->payload());
        $feedback = Feedback::first();

        $developer = $this->createUser(2, 'Developer');
        $this->actingAs($developer)
            ->post(route('dashboard.feedback.reply', $feedback), ['body' => 'Thanks, looking into it.'])
            ->assertRedirect();

        $this->assertSame(1, $feedback->replies()->count());
        $this->assertSame($developer->id, $feedback->replies()->first()->user_id);

        Notification::assertSentTo($pilot, SystemNotification::class, function (SystemNotification $notification) {
            return $notification->url === route('feedback.my.show', Feedback::first());
        });
    }

    public function test_developer_can_close_and_reopening_happens_automatically_on_reply(): void
    {
        Notification::fake();

        $pilot = $this->createUser(1, 'Pilot');
        $this->actingAs($pilot)->post(route('feedback.store'), $this->payload());
        $feedback = Feedback::first();

        $developer = $this->createUser(2, 'Developer');
        $this->actingAs($developer)->post(route('dashboard.feedback.close', $feedback))->assertRedirect();

        $this->assertTrue($feedback->fresh()->isClosed());
        Notification::assertSentTo($pilot, SystemNotification::class);

        $this->actingAs($pilot)
            ->post(route('feedback.my.reply', $feedback), ['body' => 'Still happening, please reopen.'])
            ->assertRedirect(route('feedback.my.show', $feedback));

        $this->assertFalse($feedback->fresh()->isClosed());
    }

    public function test_pilots_cannot_reply_close_or_reopen_via_the_admin_routes(): void
    {
        $pilot = $this->createUser(1, 'Pilot');
        $this->actingAs($pilot)->post(route('feedback.store'), $this->payload());
        $feedback = Feedback::first();

        $this->actingAs($pilot)->post(route('dashboard.feedback.reply', $feedback), ['body' => 'x'])->assertForbidden();
        $this->actingAs($pilot)->post(route('dashboard.feedback.close', $feedback))->assertForbidden();
        $this->actingAs($pilot)->post(route('dashboard.feedback.reopen', $feedback))->assertForbidden();
    }
}
