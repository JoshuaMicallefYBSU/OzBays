<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DiscordClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscordRoleSync implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $roleConfig = array_filter(config('services.discord.roles', []));
        $newsConfig = array_filter(config('services.discord.news_roles', []));
        $unlinkedRoleId = config('services.discord.unlinked_role');

        $managedRoleIds = array_values(array_unique(array_filter(array_merge(
            array_values($roleConfig),
            array_values($newsConfig),
            [$unlinkedRoleId]
        ))));

        $discord = app(DiscordClient::class);

        $response = $discord->getClient()->get('guilds/'.env('DISCORD_GUILD_ID').'/members?limit=1000');
        $guildMembers = json_decode($response->getBody(), true);

        $membersByDiscordId = [];
        foreach ($guildMembers as $member) {
            $membersByDiscordId[$member['user']['id']] = $member;
        }

        $excludedIds = array_map('strval', config('services.discord.excluded_ids', []));

        $linkedUsers = User::whereNotNull('discord_user_id')->with('roles', 'userPreferences')->get();
        $linkedDiscordIds = $linkedUsers->pluck('discord_user_id')->all();

        $checked = 0;
        $updated = 0;

        // Members of OzBays with a linked Discord account: sync their roles/nickname.
        foreach ($linkedUsers as $user) {
            if (in_array((string) $user->discord_user_id, $excludedIds, true)) {
                continue;
            }

            $discordMember = $membersByDiscordId[$user->discord_user_id] ?? null;
            if ($discordMember === null) {
                continue;
            }

            $checked++;

            $targetRoles = $this->targetRoleIds($user, $roleConfig, $newsConfig);

            if ($this->applyDiff($discord, $discordMember, $user->discord_user_id, $managedRoleIds, $targetRoles, $user->fullName('FLC'))) {
                $updated++;
            }
        }

        // Anyone in the Discord who isn't linked to an OzBays account: reset their nickname
        // to their raw Discord username and flag them with the "not linked" role, stripping
        // any of our other managed roles they may still be holding from a previous link.
        $unlinkedTargetRoles = array_values(array_filter([$unlinkedRoleId]));

        foreach ($membersByDiscordId as $discordId => $discordMember) {
            // Guild member array keys built from $member['user']['id'] get silently cast to int
            // by PHP for numeric-string keys, so compare as strings against the DB values.
            if (in_array((string) $discordId, $linkedDiscordIds, true) || in_array((string) $discordId, $excludedIds, true)) {
                continue;
            }

            $checked++;

            if ($this->applyDiff($discord, $discordMember, (string) $discordId, $managedRoleIds, $unlinkedTargetRoles, $discordMember['user']['username'] ?? null)) {
                $updated++;
            }
        }

        try {
            $discord->sendMessage(
                config('services.discord.'.env('APP_ENV').'.server_logs'),
                "DISCORD ROLE SYNC: {$updated}/{$checked} members updated."
            );
        } catch (\Exception $e) {
            Log::channel('discord')->error('Failed to send Discord summary message: '.$e->getMessage());
        }
    }

    /**
     * Diffs a Discord member's currently-managed roles + nickname against the target state
     * and PATCHes Discord if anything needs to change. Returns whether an update was made.
     *
     * @param  array<int, int|string>  $managedRoleIds
     * @param  array<int, int|string>  $targetRoles
     */
    private function applyDiff($discord, array $discordMember, string $discordUserId, array $managedRoleIds, array $targetRoles, ?string $nickname): bool
    {
        $currentRoles = $discordMember['roles'] ?? [];
        $currentManaged = array_intersect($currentRoles, $managedRoleIds);

        $rolesToAdd = array_values(array_diff($targetRoles, $currentManaged));
        $rolesToRemove = array_values(array_diff($currentManaged, $targetRoles));
        $finalRoles = array_values(array_unique(array_merge(array_diff($currentRoles, $rolesToRemove), $rolesToAdd)));

        $nickChanged = $nickname !== ($discordMember['nick'] ?? null);

        if (empty($rolesToAdd) && empty($rolesToRemove) && ! $nickChanged) {
            return false;
        }

        try {
            $discord->getClient()->patch('guilds/'.env('DISCORD_GUILD_ID').'/members/'.$discordUserId, [
                'json' => [
                    'nick' => $nickname,
                    'roles' => $finalRoles,
                ],
            ]);

            if (! app()->runningUnitTests()) {
                sleep(1);
            }
        } catch (\Throwable $e) {
            Log::channel('discord')->error("DiscordRoleSync failed for discord user {$discordUserId}: ".$e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @return array<int, int|string>
     */
    private function targetRoleIds(User $user, array $roleConfig, array $newsConfig): array
    {
        $targetRoles = [];

        foreach ($user->roles as $role) {
            if (! empty($roleConfig[$role->name])) {
                $targetRoles[] = $roleConfig[$role->name];
            }
        }

        $preferences = $user->userPreferences;
        foreach ($newsConfig as $preferenceKey => $discordRoleId) {
            if ($preferences !== null && ($preferences->{$preferenceKey} ?? 0) == 1) {
                $targetRoles[] = $discordRoleId;
            }
        }

        return array_values(array_unique($targetRoles));
    }
}
