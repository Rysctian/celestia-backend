<?php

namespace App\Services\Attendance;

use App\Helpers\AttendanceDuration;
use App\Helpers\DateHelper;
use App\Models\AttendanceCutoffSummary;
use App\Models\AttendanceException;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\OvertimeApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCutoffService
{
    public function ensureDateIsOpen(string $employeeId, string $date): void
    {
        $locked = AttendanceCutoffSummary::where('employee_id', $employeeId)
            ->whereDate('date_from', '<=', $date)->whereDate('date_to', '>=', $date)
            ->where(fn ($query) => $query->whereNotNull('locked_at')->orWhereNotNull('confirmed_at'))->lockForUpdate()->first();
        if ($locked) {
            throw ValidationException::withMessages(['date' => 'This date belongs to a confirmed cutoff. Reopen the cutoff before changing attendance.']);
        }
    }

    public function build(array $data): AttendanceCutoffSummary
    {
        $data['date_from'] = CarbonImmutable::parse($data['date_from'])->toDateString();
        $data['date_to'] = CarbonImmutable::parse($data['date_to'])->toDateString();

        return DB::transaction(function () use ($data) {
            Employee::where('employee_id', $data['employee_id'])->lockForUpdate()->firstOrFail();
            $this->ensureRangeIsOpen($data['employee_id'], $data['date_from'], $data['date_to']);
            foreach (DateHelper::dateRange($data['date_from'], $data['date_to']) as $date) {
                app(AttendanceProcessor::class)->reprocessAttendance($data['employee_id'], $date, false);
            }
            $attributes = [
                'employee_id' => $data['employee_id'], 'date_from' => $data['date_from'], 'date_to' => $data['date_to'],
            ];
            $summary = AttendanceCutoffSummary::where($attributes)->lockForUpdate()->first() ?? new AttendanceCutoffSummary($attributes);

            $summary = $this->accumulate($summary);
            $this->refreshOpenSummaries($data['employee_id'], $data['date_from'], $data['date_to'], $summary->id);

            return $summary;
        });
    }

    public function confirm(int $id, int $userId): AttendanceCutoffSummary
    {
        $employeeId = AttendanceCutoffSummary::findOrFail($id)->employee_id;

        return DB::transaction(function () use ($id, $userId, $employeeId) {
            $summary = $this->lockedSummary($id, $employeeId);
            if ($summary->confirmed_at || $summary->locked_at) {
                return $summary;
            }
            $summary = $this->build($summary->only(['employee_id', 'date_from', 'date_to']));
            $rows = $this->attendanceRows($summary)->get();
            $timezone = $rows->first()?->details['timezone'] ?? config('attendance.timezone');
            if ($summary->date_to->toDateString() > CarbonImmutable::today($timezone)->toDateString()
                || $rows->contains(fn ($row) => $row->sched_end->isFuture())) {
                throw ValidationException::withMessages(['cutoff' => 'A cutoff cannot be confirmed before its schedule blocks have ended.']);
            }
            if ($rows->contains('status', 'incomplete')) {
                throw ValidationException::withMessages(['cutoff' => 'Resolve incomplete attendance before confirming this cutoff.']);
            }

            $confirmedAt = now();
            $this->attendanceRows($summary)->update(['confirmed_at' => $confirmedAt]);
            $summary->update(['confirmed_at' => $confirmedAt, 'confirmed_by' => $userId, 'locked_at' => $confirmedAt]);

            return $summary;
        });
    }

    public function reopen(int $id, int $userId, string $reason): AttendanceCutoffSummary
    {
        $employeeId = AttendanceCutoffSummary::findOrFail($id)->employee_id;

        return DB::transaction(function () use ($id, $userId, $reason, $employeeId) {
            $summary = $this->lockedSummary($id, $employeeId);
            if (! $summary->confirmed_at && ! $summary->locked_at) {
                throw ValidationException::withMessages(['cutoff' => 'This cutoff is already open.']);
            }
            $details = $summary->details ?? [];
            $details['reopen_history'][] = [
                'reopened_by' => $userId, 'reopened_at' => now()->toIso8601String(), 'reason' => $reason,
                'previous_confirmed_by' => $summary->confirmed_by,
                'previous_confirmed_at' => $summary->confirmed_at?->toIso8601String(),
                'previous_totals' => $summary->only(['scheduled_seconds', 'rendered_seconds', 'credited_seconds', 'overtime_approved_seconds']),
            ];
            $summary->update(['confirmed_at' => null, 'confirmed_by' => null, 'locked_at' => null, 'details' => $details]);
            $this->attendanceRows($summary)->update(['confirmed_at' => null]);

            return $summary;
        });
    }

    public function refreshOpenSummaries(string $employeeId, string $date, ?string $dateTo = null, ?int $excludeId = null): void
    {
        $summaries = AttendanceCutoffSummary::where('employee_id', $employeeId)
            ->whereDate('date_from', '<=', $dateTo ?? $date)->whereDate('date_to', '>=', $date)
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->whereNull('locked_at')->whereNull('confirmed_at')->lockForUpdate()->get();
        foreach ($summaries as $summary) {
            $this->accumulate($summary);
        }
    }

    private function ensureRangeIsOpen(string $employeeId, string $from, string $to): void
    {
        $locked = AttendanceCutoffSummary::where('employee_id', $employeeId)
            ->whereDate('date_from', '<=', $to)->whereDate('date_to', '>=', $from)
            ->where(fn ($query) => $query->whereNotNull('locked_at')->orWhereNotNull('confirmed_at'))->lockForUpdate()->first();
        if ($locked) {
            throw ValidationException::withMessages(['cutoff' => 'This range overlaps a confirmed cutoff. Reopen it first.']);
        }
    }

    private function lockedSummary(int $id, string $employeeId): AttendanceCutoffSummary
    {
        Employee::where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();

        return AttendanceCutoffSummary::lockForUpdate()->findOrFail($id);
    }

    private function attendanceRows(AttendanceCutoffSummary $summary)
    {
        return EmployeeAttendance::where('employee_id', $summary->employee_id)
            ->whereBetween('date', [$summary->date_from->toDateString(), $summary->date_to->toDateString()]);
    }

    private function accumulate(AttendanceCutoffSummary $summary): AttendanceCutoffSummary
    {
        $rows = $this->attendanceRows($summary)->orderBy('date')->orderBy('sched_start')->lockForUpdate()->get();
        $totals = $this->emptyTotals();
        $approvals = OvertimeApplication::where('employee_id', $summary->employee_id)->where('status', 'approved')
            ->whereBetween('overtime_date', [$summary->date_from->subDay()->toDateString(), $summary->date_to->addDay()->toDateString()])->lockForUpdate()->get();

        foreach ($rows as $row) {
            foreach ($this->blockTotals($row, $approvals) as $field => $seconds) {
                $totals[$field] += $seconds;
            }
        }

        // A split day counts once: incomplete wins, then any present block, then wholly absent.
        foreach ($rows->groupBy(fn ($row) => $row->date->toDateString()) as $day) {
            if ($day->contains('status', 'incomplete')) {
                $totals['incomplete_days']++;
            } elseif ($day->contains(fn ($row) => $row->status === 'present' && ! ($row->details['overtime_only'] ?? false))) {
                $totals['present_days']++;
            } elseif ($day->every(fn ($row) => $row->absent)) {
                $totals['absent_days']++;
            }
        }
        $details = $summary->details ?? [];
        $details['attendance_ids'] = $rows->pluck('id')->all();
        $details['approved_overtime_ids'] = $approvals->pluck('id')->all();
        $details['exception_days'] = $rows->whereIn('status', AttendanceException::TYPES)
            ->pluck('date')->map(fn ($date) => $date->toDateString())->unique()->values()->all();
        $summary->fill([...$totals, 'details' => $details, 'computed_at' => now()])->save();

        return $summary;
    }

    private function emptyTotals(): array
    {
        return array_fill_keys([
            'scheduled_seconds', 'rendered_seconds', 'credited_seconds', 'late_seconds', 'undertime_seconds', 'break_seconds',
            'overtime_rendered_seconds', 'overtime_approved_seconds', 'night_diff_seconds', 'regular_seconds',
            'present_days', 'absent_days', 'incomplete_days',
        ], 0);
    }

    private function blockTotals(EmployeeAttendance $row, Collection $approvals): array
    {
        $totals = $this->emptyTotals();
        $noRegularHours = in_array($row->status, ['suspension', 'schedule_cancellation'], true) || ($row->details['overtime_only'] ?? false);
        $break = (int) ($row->details['unpaid_break_seconds'] ?? 0);
        $totals['scheduled_seconds'] = $noRegularHours ? 0 : max(0, AttendanceDuration::seconds($row->sched_start, $row->sched_end) - $break);
        $totals['late_seconds'] = $row->tardy_seconds;
        $totals['undertime_seconds'] = $row->undertime_seconds;

        if ($row->time_in && $row->time_out) {
            $overlap = AttendanceDuration::overlap($row->time_in, $row->time_out, $row->sched_start, $row->sched_end);
            $excludedBreak = min($break, $overlap);
            $totals['regular_seconds'] = $noRegularHours ? 0 : max(0, $overlap - $excludedBreak);
            $totals['break_seconds'] = $excludedBreak;
            $totals['rendered_seconds'] = max(0, AttendanceDuration::seconds($row->time_in, $row->time_out) - $excludedBreak);
            $windows = $this->overtimeWindows($approvals, $row->details['timezone']);
            foreach ($this->extraIntervals($row, $noRegularHours) as [$start, $end]) {
                $totals['overtime_rendered_seconds'] += AttendanceDuration::seconds($start, $end);
                foreach ($windows as [$approvedStart, $approvedEnd]) {
                    $totals['overtime_approved_seconds'] += AttendanceDuration::overlap($start, $end, $approvedStart, $approvedEnd);
                }
            }
            $night = AttendanceDuration::nightSeconds($row->time_in, $row->time_out, $row->details['timezone']);
            $totals['night_diff_seconds'] = max(0, $night - min($excludedBreak, $night));
        }
        if (in_array($row->status, config('attendance.credited_exceptions'), true)) {
            $totals['regular_seconds'] = $totals['scheduled_seconds'];
        }
        $totals['credited_seconds'] = $totals['regular_seconds'] + $totals['overtime_approved_seconds'];

        return $totals;
    }

    private function extraIntervals(EmployeeAttendance $row, bool $noRegularHours): array
    {
        if ($noRegularHours) {
            return [[$row->time_in, $row->time_out]];
        }
        $intervals = [];
        if ($row->time_in->lessThan($row->sched_start)) {
            $intervals[] = [$row->time_in, $row->time_out->min($row->sched_start)];
        }
        if ($row->time_out->greaterThan($row->sched_end)) {
            $intervals[] = [$row->time_in->max($row->sched_end), $row->time_out];
        }

        return $intervals;
    }

    private function overtimeWindows(Collection $approvals, string $timezone): array
    {
        $windows = [];
        foreach ($approvals as $approval) {
            $date = $approval->overtime_date->toDateString();
            $start = CarbonImmutable::parse($date.' '.$approval->time_from, $timezone);
            $end = CarbonImmutable::parse($date.' '.$approval->time_to, $timezone);
            if ($end->lessThan($start)) {
                $end = $end->addDay();
            }
            $windows[] = [$start, $end];
        }

        return AttendanceDuration::merge($windows);
    }
}
