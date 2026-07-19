<?php

namespace App\Console\Commands;

use App\Jobs\DiscordRoleSync;
use Illuminate\Console\Command;

class SyncDiscordRoles extends Command
{
    protected $signature = 'discord:sync-roles';

    protected $description = 'Sync Discord member roles/nicknames with website roles and news preferences';

    public function handle(): int
    {
        (new DiscordRoleSync)->handle();

        $this->info('Discord role sync complete. Check the discord log channel for details.');

        return self::SUCCESS;
    }
}
