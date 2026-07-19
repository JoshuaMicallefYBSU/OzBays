<?php

namespace Tests\Feature;

use App\Jobs\LiveBaysJob;
use App\Models\Bays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the live feed gate -> bay mapping (#66): a gate must only ever link to
 * the bay with that exact name, never to an unrelated bay that merely
 * contains the same characters (C4 at YMML used to resolve to B24).
 */
class LiveBaysMatchingTest extends TestCase
{
    use RefreshDatabase;

    private function createBay(string $bay, ?string $terminal = null): Bays
    {
        return Bays::create([
            'airport' => 'YMML', 'bay' => $bay, 'lat' => '-37.67', 'lon' => '144.84',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
            'terminal' => $terminal,
        ]);
    }

    public function test_gate_matches_the_exact_bay_name_not_a_substring(): void
    {
        $this->createBay('B24');
        $c4 = $this->createBay('C4');

        $match = (new LiveBaysJob)->matchBay('YMML', null, 'C4');

        $this->assertNotNull($match);
        $this->assertSame($c4->id, $match->id);
    }

    public function test_bare_gate_number_does_not_match_an_unrelated_bay(): void
    {
        // Feed splits "C4" into terminal "C" + gate "4". B24 contains a "4"
        // and previously won the substring match.
        $this->createBay('B24');
        $c4 = $this->createBay('C4');

        $match = (new LiveBaysJob)->matchBay('YMML', 'C', '4');

        $this->assertNotNull($match);
        $this->assertSame($c4->id, $match->id);
    }

    public function test_unknown_gate_returns_null_instead_of_a_wrong_bay(): void
    {
        $this->createBay('B24');

        $this->assertNull((new LiveBaysJob)->matchBay('YMML', null, 'C4'));
        $this->assertNull((new LiveBaysJob)->matchBay('YMML', null, ''));
    }

    public function test_terminal_filter_applies_when_bays_carry_terminal_data(): void
    {
        $t1 = $this->createBay('4', 'T1');
        $this->createBay('4X', 'T2');

        $match = (new LiveBaysJob)->matchBay('YMML', 'T1', '4');

        $this->assertNotNull($match);
        $this->assertSame($t1->id, $match->id);
    }

    public function test_gate_match_is_case_insensitive(): void
    {
        $c4 = $this->createBay('C4');

        $match = (new LiveBaysJob)->matchBay('YMML', null, 'c4');

        $this->assertNotNull($match);
        $this->assertSame($c4->id, $match->id);
    }
}
