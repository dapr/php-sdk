<?php

use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function get_date_intervals()
    {
        return [
            [new DateInterval('PT1S'), '0h0m1s0us'],
            [new DateInterval('PT1M'), '0h1m0s0us'],
            [null, ''],
            [new DateInterval('P3DT10M'), '72h10m0s0us'],
        ];
    }

    /**
     * @param $actual
     * @param $expected
     *
     * @dataProvider get_date_intervals
     */
    public function testNormalizeInterval($actual, $expected)
    {
        $this->assertSame($expected, \Dapr\Formats::normalize_interval($actual));
    }

    public function testYears()
    {
        $this->expectException(LogicException::class);
        \Dapr\Formats::normalize_interval(new DateInterval('P1Y'));
    }

    public function testMonths()
    {
        $this->expectException(LogicException::class);
        \Dapr\Formats::normalize_interval(new DateInterval('P1M'));
    }

    /**
     * @param $expected
     * @param $actual
     *
     * @dataProvider get_date_intervals
     */
    public function testFromDapr(?DateInterval $expected, $actual)
    {
        $converted = \Dapr\Formats::from_dapr_interval($actual);
        if ($expected && $converted->h === 72) {
            $this->assertSame(3, $expected->d);
        } else {
            $this->assertEquals($expected, $converted);
        }
    }
}
