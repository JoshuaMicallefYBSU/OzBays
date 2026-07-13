<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
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

            $pattern = (string) $pattern;
            $value = (string) $value;
            $delimited = '/'.str_replace('/', '\\/', $pattern).'/';

            return @preg_match($delimited, $value) ? 1 : 0;
        }, 2);

        $pdo->sqliteCreateFunction('CONCAT', function (...$args) {
            return implode('', array_map(fn ($value) => $value === null ? '' : (string) $value, $args));
        }, -1);

        $pdo->sqliteCreateFunction('FIND_IN_SET', function ($needle, $haystack) {
            if ($needle === null || $haystack === null) {
                return 0;
            }

            $parts = explode(',', (string) $haystack);
            $idx = array_search((string) $needle, array_map('trim', $parts), true);

            return $idx === false ? 0 : $idx + 1;
        }, 2);

        $pdo->sqliteCreateFunction('IF', function ($condition, $then, $else) {
            return $condition ? $then : $else;
        }, 3);

        $pdo->sqliteCreateFunction('RAND', function () {
            return mt_rand() / mt_getrandmax();
        }, 0);

        $pdo->sqliteCreateFunction('GREATEST', function (...$args) {
            $args = array_filter(
                array_map(fn ($value) => $value === null ? null : (float) $value, $args),
                fn ($value) => $value !== null
            );

            return empty($args) ? null : max($args);
        }, -1);
    }
}
