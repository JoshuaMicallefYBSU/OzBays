<?php

namespace Tests\Feature;

use Tests\TestCase;

class CreditsPageTest extends TestCase
{
    public function test_credits_page_lists_libraries_assets_and_packages(): void
    {
        $this->get(route('policy.credits'))
            ->assertOk()
            ->assertSee('Copyright &amp; Usage', false)
            ->assertSee('VATSIM Radar aircraft icons')
            ->assertSee('Mapbox GL JS')
            ->assertSee('laravel/framework');
    }

    public function test_generated_credits_file_is_up_to_date(): void
    {
        exec(PHP_BINARY.' '.escapeshellarg(base_path('scripts/generate-credits.php')).' --check 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
