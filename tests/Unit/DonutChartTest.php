<?php

namespace Tests\Unit;

use App\Support\DonutChart;
use PHPUnit\Framework\TestCase;

class DonutChartTest extends TestCase
{
    public function test_biggest_five_are_kept_and_the_rest_fold_into_other(): void
    {
        $chart = DonutChart::slices(collect(['B738' => 9, 'A320' => '4', 'A333' => 2, 'DH8D' => 2, 'E190' => 1, 'SF34' => 1, 'C172' => 1]), collect(['B738' => 'Boeing 737-800']));

        $this->assertSame(20, $chart['total']);
        $this->assertCount(6, $chart['slices']);
        $this->assertSame(['label' => 'B738', 'name' => 'Boeing 737-800', 'count' => 9], $chart['slices'][0]);
        $this->assertSame(4, $chart['slices'][1]['count']); // string counts from the database become ints
        $this->assertSame(['label' => 'Other', 'name' => '2 more', 'count' => 2, 'other' => true], $chart['slices'][5]);
    }

    public function test_five_or_fewer_have_no_other_slice(): void
    {
        $chart = DonutChart::slices(collect(['YSSY' => 3, 'YMML' => 1]));

        $this->assertSame(['YSSY', 'YMML'], array_column($chart['slices'], 'label'));
        $this->assertSame(0, DonutChart::slices(collect())['total']);
    }
}
