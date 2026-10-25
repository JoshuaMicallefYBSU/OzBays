<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Public Trial notice's dismiss cookie is set client-side via plain `document.cookie`
 * (no server round-trip), so it must be excluded from Laravel's cookie encryption in
 * bootstrap/app.php - otherwise request()->cookie() can never see it as present, since
 * EncryptCookies silently nulls out any cookie it fails to decrypt.
 */
class TrialNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_shows_when_no_dismiss_cookie_is_present(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Public Trial', false)
            ->assertSee('ozbays_vatpac_trial_notification', false);
    }

    public function test_notice_is_hidden_once_the_unencrypted_dismiss_cookie_is_present(): void
    {
        $this->withUnencryptedCookie('ozbays_vatpac_trial_notification', '1')
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-cookie="ozbays_vatpac_trial_notification"', false);
    }
}
