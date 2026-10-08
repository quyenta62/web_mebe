<?php

namespace Tests\Unit;

use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DisplayTimeTest extends TestCase
{
    public function test_formats_utc_times_in_the_display_timezone(): void
    {
        config(['monitor.display_timezone' => 'Asia/Ho_Chi_Minh']);

        // 17:05:09 UTC on 6 Oct = 00:05:09 on 7 Oct in Vietnam (UTC+7).
        $this->assertSame('00:05:09 07/10/2026', DisplayTime::format(CarbonImmutable::parse('2026-10-06 17:05:09', 'UTC')));
        $this->assertSame('—', DisplayTime::format(null));
    }

    public function test_does_not_modify_the_given_time(): void
    {
        $time = CarbonImmutable::parse('2026-10-07 03:30:00', 'UTC')->toMutable();

        DisplayTime::format($time);

        $this->assertSame('UTC', $time->getTimezone()->getName());
    }
}
