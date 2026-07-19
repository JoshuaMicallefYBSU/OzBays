<?php

namespace Tests\Feature;

use App\Jobs\DiscordRoleSync;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\FakeDiscordClient;
use Tests\TestCase;

class DiscordRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeDiscordClient $discord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discord = new FakeDiscordClient;
        $this->app->instance(\App\Services\DiscordClient::class, $this->discord);

        config([
            'services.discord.roles' => [
                'Lead Developer' => 'discord-lead-dev-role',
                'Developer' => 'discord-dev-role',
                'Maintainer' => null,
                'Contributor' => null,
                'Pilot' => null,
            ],
            'services.discord.news_roles' => [
                'news_general' => 'discord-news-general-role',
                'news_notifications' => null,
                'ozbays_updates' => null,
            ],
            'services.discord.unlinked_role' => 'discord-unlinked-role',
            'services.discord.excluded_ids' => ['999'],
        ]);
    }

    /**
     * Inserted directly via the query builder — going through User::create() fires a
     * created() hook that assumes an autoincrement id, which this custom (VATSIM-cid-keyed,
     * non-autoincrement) users table doesn't actually have. That's a separate, unrelated bug
     * outside the scope of this feature, so we sidestep it here rather than depending on it.
     */
    private function createLinkedUser(int $id, string $discordUserId, string $roleName): User
    {
        DB::table('users')->insert([
            'id' => $id, 'fname' => 'Test', 'lname' => 'Pilot', 'email' => "{$discordUserId}@example.com",
            'discord_user_id' => $discordUserId, 'discord_member' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        UserPreference::create(['user_id' => $id, 'news_general' => 1]);

        $user = User::find($id);
        $user->assignRole($roleName);

        return $user;
    }

    public function test_patches_role_and_nickname_for_a_linked_user_missing_their_role(): void
    {
        $user = $this->createLinkedUser(2, '222', 'Developer');

        $this->discord->guildMembersResponse = [
            ['user' => ['id' => '222'], 'nick' => null, 'roles' => []],
        ];

        (new DiscordRoleSync)->handle();

        $patches = $this->discord->getClient()->patches;
        $this->assertCount(1, $patches);
        $this->assertStringContainsString('222', $patches[0]['uri']);
        $this->assertContains('discord-dev-role', $patches[0]['json']['roles']);
        $this->assertContains('discord-news-general-role', $patches[0]['json']['roles']);
        $this->assertSame($user->fullName('FLC'), $patches[0]['json']['nick']);
    }

    public function test_user_already_matching_their_target_roles_and_nickname_is_left_alone(): void
    {
        $user = $this->createLinkedUser(3, '333', 'Developer');

        $this->discord->guildMembersResponse = [
            [
                'user' => ['id' => '333'],
                'nick' => $user->fullName('FLC'),
                'roles' => ['discord-dev-role', 'discord-news-general-role', 'some-unmanaged-role'],
            ],
        ];

        (new DiscordRoleSync)->handle();

        $this->assertEmpty($this->discord->getClient()->patches);
    }

    public function test_unmanaged_roles_the_member_already_has_are_preserved(): void
    {
        $this->createLinkedUser(4, '444', 'Developer');

        $this->discord->guildMembersResponse = [
            ['user' => ['id' => '444'], 'nick' => null, 'roles' => ['some-unmanaged-role']],
        ];

        (new DiscordRoleSync)->handle();

        $patches = $this->discord->getClient()->patches;
        $this->assertCount(1, $patches);
        $this->assertContains('some-unmanaged-role', $patches[0]['json']['roles']);
    }

    public function test_user_not_present_in_the_guild_is_skipped(): void
    {
        $this->createLinkedUser(5, '555', 'Developer');

        $this->discord->guildMembersResponse = [];

        (new DiscordRoleSync)->handle();

        $this->assertEmpty($this->discord->getClient()->patches);
    }

    public function test_guild_member_with_no_matching_ozbays_account_is_reset_to_their_discord_username_and_unlinked_role(): void
    {
        $this->discord->guildMembersResponse = [
            [
                'user' => ['id' => '666', 'username' => 'RandomDiscordUser'],
                'nick' => 'Some Old Nickname',
                'roles' => [],
            ],
        ];

        (new DiscordRoleSync)->handle();

        $patches = $this->discord->getClient()->patches;
        $this->assertCount(1, $patches);
        $this->assertStringContainsString('666', $patches[0]['uri']);
        $this->assertSame('RandomDiscordUser', $patches[0]['json']['nick']);
        $this->assertSame(['discord-unlinked-role'], $patches[0]['json']['roles']);
    }

    public function test_previously_linked_managed_roles_are_stripped_once_the_ozbays_account_is_gone(): void
    {
        // Still holding a managed role from a past link, but no OzBays user references this
        // discord id any more (account deleted/unlinked).
        $this->discord->guildMembersResponse = [
            [
                'user' => ['id' => '777', 'username' => 'FormerMember'],
                'nick' => 'Old Nick',
                'roles' => ['discord-dev-role', 'discord-news-general-role', 'some-unmanaged-role'],
            ],
        ];

        (new DiscordRoleSync)->handle();

        $patches = $this->discord->getClient()->patches;
        $this->assertCount(1, $patches);
        $this->assertSame('FormerMember', $patches[0]['json']['nick']);
        $this->assertContains('discord-unlinked-role', $patches[0]['json']['roles']);
        $this->assertContains('some-unmanaged-role', $patches[0]['json']['roles']);
        $this->assertNotContains('discord-dev-role', $patches[0]['json']['roles']);
        $this->assertNotContains('discord-news-general-role', $patches[0]['json']['roles']);
    }

    public function test_excluded_linked_user_is_never_patched(): void
    {
        // discord_user_id '999' is configured as excluded in setUp() (e.g. the server owner).
        $this->createLinkedUser(6, '999', 'Developer');

        $this->discord->guildMembersResponse = [
            ['user' => ['id' => '999'], 'nick' => null, 'roles' => []],
        ];

        (new DiscordRoleSync)->handle();

        $this->assertEmpty($this->discord->getClient()->patches);
    }

    public function test_excluded_unlinked_member_is_never_patched(): void
    {
        // e.g. the bot's own account showing up in the member list.
        $this->discord->guildMembersResponse = [
            ['user' => ['id' => '999', 'username' => 'OzBaysBot'], 'nick' => null, 'roles' => []],
        ];

        (new DiscordRoleSync)->handle();

        $this->assertEmpty($this->discord->getClient()->patches);
    }
}
