<?php

namespace App\Support;

use Carbon\CarbonInterface;

class DisplayTime
{
    /** Formats a UTC timestamp in the display timezone; '—' when empty. */
    public static function format(?CarbonInterface $time, string $format = 'Y-m-d H:i'): string
    {
        return $time?->copy()->setTimezone(config('monitor.display_timezone'))->format($format) ?? '—';
    }
}
