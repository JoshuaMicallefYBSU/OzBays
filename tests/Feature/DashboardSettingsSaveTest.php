<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_settings_persists_the_new_news_category_preferences(): void
    {
        DB::table('users')->insert([
            'id' => 1, 'fname' => 'Test', 'lname' => 'Pilot', 'email' => 'pilot@example.com',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find(1);
        UserPreference::create(['user_id' => 1]);

        $response = $this->actingAs($user)->post(route('dashboard.settings.save'), [
            'id' => $user->id,
            'name_format' => 2,
            'hoppie_usage' => 1,
            'email_feedback' => 1,
            'news_notifications' => 0,
            'news_general' => 0,
            'ozbays_updates' => 1,
        ]);

        $response->assertRedirect();

        $preferences = UserPreference::where('user_id', $user->id)->first();
        $this->assertSame(0, $preferences->news_notifications);
        $this->assertSame(0, $preferences->news_general);
        $this->assertSame(1, $preferences->ozbays_updates);
    }
}
