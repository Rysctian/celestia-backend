<?php

namespace App\Helpers;

use Carbon\CarbonImmutable;

class AttendanceDuration
{
    public static function seconds(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return max(0, $end->getTimestamp() - $start->getTimestamp());
    }

    public static function overlap(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $otherStart, CarbonImmutable $otherEnd): int
    {
        return self::seconds($start->max($otherStart), $end->min($otherEnd));
    }

    /** Merge approval windows so overlapping applications never credit time twice. */
    public static function merge(array $intervals): array
    {
        usort($intervals, fn ($a, $b) => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start->lessThanOrEqualTo($merged[$last][1])) {
                $merged[$last][1] = $merged[$last][1]->max($end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    public static function nightSeconds(CarbonImmutable $start, CarbonImmutable $end, string $timezone): int
    {
        $seconds = 0;
        $lastDate = $end->setTimezone($timezone)->startOfDay();
        for ($date = $start->setTimezone($timezone)->startOfDay()->subDay(); $date->lessThanOrEqualTo($lastDate); $date = $date->addDay()) {
            $nightStart = CarbonImmutable::parse($date->toDateString().' '.config('attendance.night_start'), $timezone);
            $nightEnd = CarbonImmutable::parse($date->toDateString().' '.config('attendance.night_end'), $timezone);
            if ($nightEnd->lessThanOrEqualTo($nightStart)) {
                $nightEnd = $nightEnd->addDay();
            }
            $seconds += self::overlap($start, $end, $nightStart, $nightEnd);
        }

        return $seconds;
    }
}
