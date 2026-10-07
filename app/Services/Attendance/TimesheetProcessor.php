<?php

namespace App\Services\Attendance;

use App\Models\EmployeeLog;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;

class TimesheetProcessor
{
    public function __construct(private AttendanceScheduleService $schedules, private AttendanceCutoffService $cutoffs) {}

    public function process(string $employeeId, string $date, array $day): array
    {
        // Clear old interpretations even when a changed schedule moves a tap outside the new window.
        Timesheet::where('employee_id', $employeeId)->whereDate('attendance_date', $date)->update([
            'schedule_id' => null, 'schedule_detail_id' => null, 'overtime_application_id' => null,
            'log_type' => null, 'status' => 'unmatched', 'reason' => 'No matching schedule after reprocessing.', 'processed_at' => now(),
        ]);
        $blocks = $this->schedules->nearbyBlocks($employeeId, $date);
        $start = CarbonImmutable::parse($date, $day['timezone']);
        $end = $start->addDay();
        foreach ($day['blocks'] as $block) {
            $start = $start->min($block['start']->subSeconds(config('attendance.arrival_window_seconds')));
            $end = $end->max($block['end']->addSeconds(config('attendance.departure_window_seconds')));
        }

        $logs = EmployeeLog::with('timesheet')->where('employee_id', $employeeId)
            ->whereBetween('logged_at', [$start->utc()->toDateTimeString(), $end->utc()->toDateTimeString()])
            ->orderBy('logged_at')->orderBy('id')->lockForUpdate()->get();
        $groups = [];
        $affectedDates = [];
        foreach ($logs as $log) {
            $candidate = $this->nearestBoundary($log, $blocks);
            $logDate = $log->logged_at->setTimezone($day['timezone'])->toDateString();
            if (($candidate && $candidate['block']['date'] !== $date) || (! $candidate && $logDate !== $date)) {
                continue;
            }
            if ($log->timesheet && $log->timesheet->attendance_date->toDateString() !== $date) {
                $previousDate = $log->timesheet->attendance_date->toDateString();
                $this->cutoffs->ensureDateIsOpen($employeeId, $previousDate);
                $affectedDates[$previousDate] = true;
            }

            $sheet = Timesheet::updateOrCreate(['employee_log_id' => $log->id], [
                'employee_id' => $employeeId, 'attendance_date' => $date, 'logged_at' => $log->logged_at,
                'schedule_id' => $candidate['block']['schedule_id'] ?? null,
                'schedule_detail_id' => $candidate['block']['detail_id'] ?? null,
                'overtime_application_id' => $candidate['block']['overtime_application_id'] ?? null,
                'log_type' => null, 'status' => 'unmatched',
                'reason' => $candidate ? 'Additional tap near the same schedule boundary.' : 'No matching schedule boundary.',
                'processed_at' => now(),
            ]);
            if ($candidate) {
                $key = $candidate['block']['key'].':'.$candidate['type'];
                $groups[$key]['type'] = $candidate['type'];
                $groups[$key]['sheets'][] = $sheet;
            }
        }

        foreach ($groups as $group) {
            $unique = [];
            $previous = null;
            foreach ($group['sheets'] as $sheet) {
                if ($previous && $sheet->logged_at->getTimestamp() - $previous->logged_at->getTimestamp() <= config('attendance.duplicate_seconds')) {
                    $sheet->update(['status' => 'duplicate', 'reason' => 'Repeated tap within the duplicate window.']);

                    continue;
                }
                $unique[] = $sheet;
                $previous = $sheet;
            }
            $selected = $group['type'] === 'in' ? $unique[0] : $unique[count($unique) - 1];
            foreach ($unique as $sheet) {
                $sheet->update($sheet->id === $selected->id
                    ? ['log_type' => $group['type'], 'status' => 'selected', 'reason' => null]
                    : ['status' => 'ignored', 'reason' => 'Another tap was selected for this schedule boundary.']);
            }
        }

        return [
            'timesheets' => Timesheet::where('employee_id', $employeeId)->whereDate('attendance_date', $date)
                ->orderBy('logged_at')->orderBy('employee_log_id')->get(),
            'affected_dates' => array_keys($affectedDates),
        ];
    }

    private function nearestBoundary(EmployeeLog $log, array $blocks): ?array
    {
        $best = null;
        $ambiguous = false;
        foreach ($blocks as $block) {
            if ($log->logged_at->lessThan($block['start']->subSeconds(config('attendance.arrival_window_seconds')))
                || $log->logged_at->greaterThan($block['end']->addSeconds(config('attendance.departure_window_seconds')))) {
                continue;
            }
            // OUT wins an exact tie at adjoining blocks; a raw tap is never used twice.
            foreach (['out' => $block['end'], 'in' => $block['start']] as $type => $boundary) {
                $distance = abs($log->logged_at->getTimestamp() - $boundary->getTimestamp());
                if ($best === null || $distance < $best['distance']) {
                    $best = ['block' => $block, 'type' => $type, 'distance' => $distance];
                    $ambiguous = false;
                } elseif ($distance > 0 && $distance === $best['distance']) {
                    $ambiguous = true;
                }
            }
        }

        return $ambiguous ? null : $best;
    }
}
