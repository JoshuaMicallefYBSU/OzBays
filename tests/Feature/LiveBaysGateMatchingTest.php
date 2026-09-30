<?php

namespace Tests\Feature;

use App\Jobs\LiveBaysJob;
use App\Models\Bays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #66 - IRL gates were matched to bays with a substring search, so gate "4" at YMML T1 linked to B24.
 */
class LiveBaysGateMatchingTest extends TestCase
{
    use RefreshDatabase;

    private function bay(string $name, string $terminal = 'T1'): Bays
    {
        return Bays::create([
            'airport' => 'YMML', 'bay' => $name, 'lat' => '0', 'lon' => '0', 'aircraft' => 'B738',
            'priority' => 1, 'terminal' => $terminal, 'check_exist' => 1,
        ]);
    }

    private function findBay(string $gate, ?string $terminal = 'T1'): ?Bays
    {
        $method = (new \ReflectionClass(LiveBaysJob::class))->getMethod('findBay');
        $method->setAccessible(true);

        return $method->invoke(new LiveBaysJob, ['arrival' => 'YMML', 'terminal' => $terminal, 'gate' => $gate]);
    }

    public function test_gate_number_matches_its_own_bay_not_one_containing_the_digit(): void
    {
        $this->bay('B24');
        $c4 = $this->bay('C4');

        $this->assertSame($c4->id, $this->findBay('4')?->id);
    }

    public function test_exact_bay_name_wins(): void
    {
        $this->bay('C4');
        $c4a = $this->bay('C4A');

        $this->assertSame($c4a->id, $this->findBay('C4A')?->id);
    }

    public function test_ambiguous_gate_is_not_linked(): void
    {
        $this->bay('C4');
        $this->bay('D4');

        $this->assertNull($this->findBay('4'));
    }

    public function test_terminal_narrows_the_match(): void
    {
        $this->bay('D4', 'T3');
        $c4 = $this->bay('C4', 'T1');

        $this->assertSame($c4->id, $this->findBay('4', 'T1')?->id);
    }

    public function test_no_matching_bay_returns_null(): void
    {
        $this->bay('B24');

        $this->assertNull($this->findBay('4'));
    }
}
