<?php

namespace Tests\Concerns;

use App\Services\DiscordClient;
use Illuminate\Support\Facades\DB;

/**
 * BayAllocation relies on MySQL-only functions (REGEXP, FIND_IN_SET, GREATEST, ...)
 * in raw query fragments. The test suite runs on sqlite, so these shims translate
 * those calls into their PHP equivalents for the duration of the test.
 */
trait InteractsWithBayAllocationSqlite
{
    protected function registerBayAllocationSqliteShims(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        if (! method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        $pdo->sqliteCreateFunction('REGEXP', function ($pattern, $value) {
            if ($pattern === null || $value === null) {
                return 0;
            }

            $delimited = '/'.str_replace('/', '\\/', (string) $pattern).'/';

            return @preg_match($delimited, (string) $value) ? 1 : 0;
        }, 2);

        $pdo->sqliteCreateFunction('CONCAT', function (...$args) {
            return implode('', array_map(fn ($v) => $v === null ? '' : (string) $v, $args));
        }, -1);

        $pdo->sqliteCreateFunction('FIND_IN_SET', function ($needle, $haystack) {
            if ($needle === null || $haystack === null) {
                return 0;
            }

            $parts = array_map('trim', $haystack === '' ? [] : explode(',', (string) $haystack));
            $idx = array_search((string) $needle, $parts, true);

            return $idx === false ? 0 : ($idx + 1);
        }, 2);

        $pdo->sqliteCreateFunction('IF', function ($cond, $then, $else) {
            return $cond ? $then : $else;
        }, 3);

        $pdo->sqliteCreateFunction('RAND', function () {
            return mt_rand() / mt_getrandmax();
        }, 0);

        $pdo->sqliteCreateFunction('GREATEST', function (...$args) {
            $args = array_values(array_filter(
                array_map(fn ($v) => $v === null ? null : (float) $v, $args),
                fn ($v) => $v !== null
            ));

            return empty($args) ? null : max($args);
        }, -1);
    }

    protected function fakeDiscordClient(): FakeDiscordClient
    {
        $fake = new FakeDiscordClient;
        $this->app->instance(DiscordClient::class, $fake);

        return $fake;
    }
}
