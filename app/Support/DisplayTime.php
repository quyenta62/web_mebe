<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The one date/time format of the UI, e.g. "10:30:00 07/10/2026" (display timezone).
 * Timestamps are stored in UTC and converted here.
 */
class DisplayTime
{
    public const FORMAT = 'H:i:s d/m/Y';

    /** Formats a UTC timestamp in the display timezone; '—' when empty. */
    public static function format(?CarbonInterface $time): string
    {
        return $time?->copy()->setTimezone(config('monitor.display_timezone'))->format(self::FORMAT) ?? '—';
    }
}
