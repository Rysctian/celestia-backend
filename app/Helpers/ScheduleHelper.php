<?php

namespace App\Helpers;

use App\Models\Schedule;
use Carbon\CarbonImmutable;

class ScheduleHelper
{
    public static function shiftsForDate(Schedule $schedule, CarbonImmutable $date): array
    {
        $shifts = [];
        foreach ($schedule->details->where('day_of_week', $date->isoWeekday()) as $detail) {
            if ($detail->is_rest_day) {
                continue;
            }

            $start = CarbonImmutable::parse($date->toDateString().' '.$detail->start_time, $schedule->timezone);
            $end = CarbonImmutable::parse($date->toDateString().' '.$detail->end_time, $schedule->timezone);
            if ($end->lessThan($start)) {
                $end = $end->addDay();
            }
            $shifts[] = [
                'detail_id' => $detail->id, 'schedule_id' => $schedule->id,
                'date' => $date->toDateString(), 'timezone' => $schedule->timezone,
                'start' => $start, 'end' => $end, 'unpaid_break_minutes' => $detail->unpaid_break_minutes,
            ];
        }

        return $shifts;
    }
}
