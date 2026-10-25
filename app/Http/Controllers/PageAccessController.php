<?php

namespace App\Http\Controllers;

use App\Models\PageView;

class PageAccessController extends Controller
{
    // Pages tracked by TrackPageView, in display order.
    private const PAGES = [
        'home' => 'Homepage',
        'map' => 'Live Map',
        'airport_ladder' => 'Airport Ladder',
        'dashboard' => 'Dashboard',
    ];

    public function index()
    {
        $ranges = [
            '24h' => ['label' => 'Last 24 Hours', 'since' => now()->subHours(24)],
            '7d' => ['label' => 'Last 7 Days', 'since' => now()->subDays(7)],
            '30d' => ['label' => 'Last 30 Days', 'since' => now()->subDays(30)],
        ];

        $summary = [];
        foreach ($ranges as $rangeKey => $range) {
            foreach (self::PAGES as $pageKey => $label) {
                $summary[$rangeKey][$pageKey] = PageView::where('page', $pageKey)
                    ->where('created_at', '>=', $range['since'])
                    ->count();
            }
        }

        // Daily totals for the last 30 days, per page - zero-filled so every day has an
        // entry even when nothing was recorded, keeping the chart's axis complete.
        $daily = [];
        foreach (self::PAGES as $pageKey => $label) {
            $counts = PageView::where('page', $pageKey)
                ->where('created_at', '>=', now()->subDays(29)->startOfDay())
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');

            $series = [];
            for ($i = 29; $i >= 0; $i--) {
                $day = now()->subDays($i)->toDateString();
                $series[$day] = (int) ($counts[$day] ?? 0);
            }

            $daily[$pageKey] = $series;
        }

        return view('dashboard.admin.page-access.index', [
            'pages' => self::PAGES,
            'ranges' => $ranges,
            'summary' => $summary,
            'daily' => $daily,
        ]);
    }
}
