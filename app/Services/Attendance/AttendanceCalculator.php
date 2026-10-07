<?php

namespace App\Services\Attendance;

use App\Models\AttendanceException;
use App\Models\EmployeeAttendance;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    public function calculate(string $employeeId, array $block, Collection $sheets, ?AttendanceException $exception): EmployeeAttendance
    {
        $overtimeOnly = $block['overtime_application_id'] !== null;
        $attributes = [
            'employee_id' => $employeeId, 'date' => $block['date'], 'schedule_detail_id' => $block['detail_id'],
            'overtime_application_id' => $block['overtime_application_id'],
        ];
        $attendance = EmployeeAttendance::where($attributes)->lockForUpdate()->first() ?? new EmployeeAttendance($attributes);
        $selected = $sheets->where('schedule_detail_id', $block['detail_id'])
            ->where('overtime_application_id', $block['overtime_application_id'])->where('status', 'selected');
        $in = $selected->firstWhere('log_type', 'in')?->logged_at;
        $out = $selected->firstWhere('log_type', 'out')?->logged_at;
        $correction = $attendance->exists ? $attendance->corrections()->orderByDesc('id')->lockForUpdate()->first() : null;
        if ($correction) {
            $in = $correction->time_in;
            $out = $correction->time_out;
        }

        $tardy = $in ? max(0, min($in->getTimestamp(), $block['end']->getTimestamp()) - $block['start']->getTimestamp()) : 0;
        $undertime = $out ? max(0, $block['end']->getTimestamp() - max($out->getTimestamp(), $block['start']->getTimestamp())) : 0;
        $status = $in && $out ? 'present' : ($in || $out ? 'incomplete' : 'absent');
        $remarks = match ($status) {
            'absent' => 'No valid attendance logs.',
            'incomplete' => $in ? 'Missing time-out.' : 'Missing time-in.',
            default => null,
        };
        if ($exception) {
            $status = $exception->type;
            $remarks = $exception->reason;
            $tardy = $undertime = 0;
        }
        if ($overtimeOnly) {
            $tardy = $undertime = 0;
            if ($status === 'absent') {
                $status = 'overtime_not_rendered';
            }
        }

        $attendance->fill([
            'schedule_id' => $block['schedule_id'], 'sched_start' => $block['start']->utc(), 'sched_end' => $block['end']->utc(),
            'time_in' => $in, 'time_out' => $out, 'tardy' => $tardy > 0, 'tardy_seconds' => $tardy,
            'undertime' => $undertime > 0, 'undertime_seconds' => $undertime,
            'absent' => $status === 'absent', 'missing_time_in' => $in === null, 'missing_time_out' => $out === null,
            // No separate early-dismissal policy exists yet; undertime is authoritative.
            'early_dismiss' => false, 'early_dismiss_seconds' => 0,
            'status' => $status, 'remarks' => $correction ? $correction->reason : $remarks,
            'details' => [
                'timezone' => $block['timezone'], 'unpaid_break_seconds' => $block['unpaid_break_minutes'] * 60,
                'selected_log_ids' => $selected->pluck('employee_log_id')->values()->all(),
                'exception_id' => $exception?->id, 'correction_id' => $correction?->id,
                'overtime_only' => $overtimeOnly,
            ],
            'computed_at' => now(), 'confirmed_at' => null,
        ])->save();

        return $attendance;
    }
}
