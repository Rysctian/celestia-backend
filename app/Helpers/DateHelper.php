<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Generate an array of dates between two dates.
     *
     * @param  string|Carbon  $from
     * @param  string|Carbon  $to
     * @param  string  $format
     */
    public static function dateRange($from, $to, $format = 'Y-m-d'): array
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        $dates = [];

        while ($start->lte($end)) {
            $dates[] = $start->format($format);
            $start->addDay();
        }

        return $dates;
    }
}
