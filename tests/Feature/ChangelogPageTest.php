<?php

namespace Tests\Feature;

use App\Models\Changelog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    private function entries(): array
    {
        $older = Changelog::create(['title' => 'V0.10.0', 'description' => 'Older changes', 'url' => 'https://github.com/x/pull/81', 'pr_number' => 81, 'merged_at' => now()->subDay()]);
        $latest = Changelog::create(['title' => 'V0.10.1', 'description' => 'Latest changes', 'url' => 'https://github.com/x/pull/84', 'pr_number' => 84, 'merged_at' => now()]);

        return [$latest, $older];
    }

    public function test_footer_link_opens_the_latest_entry(): void
    {
        [$latest, $older] = $this->entries();

        $this->get(route('changelog.index', ['open' => 'latest']))
            ->assertOk()
            ->assertSee('class="collapse oz-changelog-desc show" id="changelog-desc-'.$latest->id.'"', false)
            ->assertSee('class="collapse oz-changelog-desc " id="changelog-desc-'.$older->id.'"', false);
    }

    public function test_entries_start_collapsed_without_the_link(): void
    {
        [$latest] = $this->entries();

        $this->get(route('changelog.index'))
            ->assertOk()
            ->assertSee('class="collapse oz-changelog-desc " id="changelog-desc-'.$latest->id.'"', false);
    }

    public function test_footer_links_to_the_latest_entry(): void
    {
        $this->get(route('changelog.index'))->assertSee('href="'.e(route('changelog.index', ['open' => 'latest'])).'"', false);
    }
}
