<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    public function calculate(string $date, Collection $schedules, Collection $logs): array
    {
        $schedules = $schedules->where('is_rest_day', false)->values();

        $firstSchedule = $schedules->first();
        $lastSchedule = $schedules->last();

        $scheduleStart = Carbon::parse("$date {$firstSchedule->start_time}");
        $scheduleEnd = Carbon::parse("$date {$lastSchedule->end_time}");

        $tardyStart = Carbon::parse(
            "$date ".($firstSchedule->tardy_start ?? $firstSchedule->start_time)
        );

        $undertimeStart = Carbon::parse(
            "$date ".($lastSchedule->undertime_start ?? $lastSchedule->end_time)
        );

        $earlyDismissalStart = Carbon::parse(
            "$date ".($lastSchedule->early_dismissal_start ?? $lastSchedule->end_time)
        );

        $timeIn = $logs->first()?->logged_at;
        $timeOut = $logs->count() > 1 ? $logs->last()?->logged_at : null;

        $scheduledSeconds = $this->scheduledSeconds($date, $schedules);

        if (! $timeIn) {
            return [
                'sched_start' => $scheduleStart->format('H:i:s'),
                'sched_end' => $scheduleEnd->format('H:i:s'),
                'time_in' => null,
                'time_out' => null,
                'scheduled_seconds' => $scheduledSeconds,
                'rendered_seconds' => 0,
                'tardy_seconds' => 0,
                'undertime_seconds' => $scheduledSeconds,
                'early_out_seconds' => 0,
                'absent' => true,
                'remarks' => 'absent',
            ];
        }

        $tardySeconds = $timeIn->greaterThan($tardyStart) ? (int) $tardyStart->diffInSeconds($timeIn) : 0;
        $undertimeSeconds = $timeOut && $timeOut->lessThan($undertimeStart) ? (int) $timeOut->diffInSeconds($scheduleEnd) : 0;
        $earlyOutSeconds = $timeOut && $timeOut->lessThan($earlyDismissalStart) ? (int) $timeOut->diffInSeconds($scheduleEnd) : 0;
        $renderedSeconds = $this->renderedSeconds($date, $schedules, $timeIn, $timeOut);
        $absent = $renderedSeconds === 0;

        return [
            'sched_start' => $scheduleStart->format('H:i:s'),
            'sched_end' => $scheduleEnd->format('H:i:s'),
            'time_in' => $timeIn?->format('H:i:s'),
            'time_out' => $timeOut?->format('H:i:s'),

            'scheduled_seconds' => $scheduledSeconds,
            'rendered_seconds' => $renderedSeconds,

            'tardy_seconds' => $tardySeconds,
            'undertime_seconds' => $absent ? $scheduledSeconds : min($undertimeSeconds, $scheduledSeconds),
            'early_out_seconds' => $earlyOutSeconds,

            'absent' => $absent,
            'remarks' => $absent ? 'absent' : 'present',
        ];
    }

    private function scheduledSeconds(string $date, Collection $schedules): int
    {
        return (int) $schedules->sum(function ($schedule) use ($date) {
            $start = Carbon::parse("$date {$schedule->start_time}");
            $end = Carbon::parse("$date {$schedule->end_time}");

            return $start->diffInSeconds($end);
        });
    }

    private function renderedSeconds(
        string $date,
        Collection $schedules,
        ?CarbonInterface $timeIn,
        ?CarbonInterface $timeOut
    ): int {
        if (! $timeIn || ! $timeOut) {
            return 0;
        }

        return (int) $schedules->sum(function ($schedule) use ($date, $timeIn, $timeOut) {
            $start = Carbon::parse("$date {$schedule->start_time}");
            $end = Carbon::parse("$date {$schedule->end_time}");

            $actualStart = $timeIn->greaterThan($start) ? $timeIn : $start;
            $actualEnd = $timeOut->lessThan($end) ? $timeOut : $end;

            return $actualEnd->greaterThan($actualStart)
                ? $actualStart->diffInSeconds($actualEnd)
                : 0;
        });
    }
}
