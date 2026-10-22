<?php

namespace App\Support;

use Illuminate\Support\Collection;

// Data for resources/views/partials/donut-chart.blade.php
class DonutChart
{
    /**
     * Slices from [label => count] - the five biggest, the rest folded into "Other" so each slice keeps a distinct
     * colour. $names adds a description to each label (e.g. QFA -> Qantas).
     */
    public static function slices(Collection $counts, ?Collection $names = null): array
    {
        $counts = $counts->map(fn ($total) => (int) $total)->sortDesc();
        $total = $counts->sum();

        $slices = $counts->take(5)->map(fn ($count, $label) => [
            'label' => (string) $label,
            'name' => $names?->get($label),
            'count' => $count,
        ])->values();

        $other = $counts->slice(5)->sum();
        if ($other > 0) {
            $slices->push(['label' => 'Other', 'name' => ($counts->count() - 5).' more', 'count' => $other, 'other' => true]);
        }

        return ['total' => $total, 'slices' => $slices->all()];
    }
}
