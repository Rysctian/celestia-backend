<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeScheduleService
{
    public function findAll(string $employee_id)
    {
        return EmployeeSchedule::with('schedule.details')->where('employee_id', $employee_id)
            ->orderBy('effective_from')->get();
    }

    public function assignSchedule(array $data, int $assigned_by)
    {
        return DB::transaction(function () use ($data, $assigned_by) {
            // Lock the template too, so assignment cannot race a template edit.
            $schedule = Schedule::with('details')->lockForUpdate()->findOrFail($data['schedule_id']);
            if (! $schedule->is_active || $schedule->details->pluck('day_of_week')->unique()->count() !== 7) {
                throw ValidationException::withMessages(['schedule_id' => 'Select an active schedule with all seven days configured.']);
            }

            // A stable employee lock protects even employees with no assignments yet.
            $employees = Employee::whereIn('employee_id', $data['employee_ids'])
                ->orderBy('employee_id')->lockForUpdate()->get();
            if ($employees->count() !== count($data['employee_ids'])) {
                throw ValidationException::withMessages(['employee_ids' => 'One or more employees no longer exist.']);
            }

            $assignments = collect();
            foreach ($employees as $employee) {
                $assignment = new EmployeeSchedule([
                    'employee_id' => $employee->employee_id,
                    'schedule_id' => $schedule->id,
                    'effective_from' => $data['effective_from'],
                    'effective_to' => $data['effective_to'] ?? null,
                    'assigned_by' => $assigned_by,
                ]);
                $assignment->setRelation('schedule', $schedule);
                // A locking read sees assignments committed while we waited for the employee lock.
                $existing = EmployeeSchedule::with('schedule.details')->where('employee_id', $employee->employee_id)->lockForUpdate()->get();

                $this->ensureNoOverlap($assignment, $existing);
                $assignment->save();
                $assignments->push($assignment);
            }

            return $assignments;
        });
    }

    public function updateAssignment(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $assignment = EmployeeSchedule::findOrFail($id);
            Employee::where('employee_id', $assignment->employee_id)->lockForUpdate()->firstOrFail();
            $assignment = EmployeeSchedule::with('schedule.details')->lockForUpdate()->findOrFail($id);
            $today = CarbonImmutable::today($assignment->schedule->timezone)->toDateString();

            if (($assignment->effective_to && $assignment->effective_to->toDateString() < $today)
                || $data['effective_to'] < $today
                || $data['effective_to'] < $assignment->effective_from->toDateString()) {
                throw ValidationException::withMessages(['effective_to' => 'Only current or future assignments can be changed. The end date must be today or later and on or after the start date.']);
            }

            $assignment->effective_to = $data['effective_to'];
            $existing = EmployeeSchedule::with('schedule.details')->where('employee_id', $assignment->employee_id)
                ->whereKeyNot($assignment->id)->lockForUpdate()->get();
            $this->ensureNoOverlap($assignment, $existing);
            $assignment->save();

            return $assignment;
        });
    }

    public function findForRange(string $employee_id, array $data)
    {
        Employee::where('employee_id', $employee_id)->firstOrFail();
        $assignments = EmployeeSchedule::with('schedule.details')->where('employee_id', $employee_id)
            ->whereDate('effective_from', '<=', $data['to'])
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['from']))
            ->orderBy('effective_from')->get();

        $result = [];
        for ($date = CarbonImmutable::parse($data['from']); $date->toDateString() <= $data['to']; $date = $date->addDay()) {
            $assignment = $assignments->first(fn ($item) => $item->effective_from->toDateString() <= $date->toDateString()
                && (! $item->effective_to || $item->effective_to->toDateString() >= $date->toDateString()));

            if (! $assignment) {
                $result[] = ['work_date' => $date->toDateString(), 'status' => 'unassigned'];

                continue;
            }

            $shifts = $this->shiftsForDate($assignment->schedule, $date);
            $result[] = [
                'work_date' => $date->toDateString(),
                'status' => $shifts === [] ? 'rest_day' : 'working',
                'assignment_id' => $assignment->id,
                'schedule_id' => $assignment->schedule_id,
                'schedule_name' => $assignment->schedule->name,
                'timezone' => $assignment->schedule->timezone,
                'shifts' => array_map(fn ($shift) => [
                    'detail_id' => $shift['detail_id'],
                    'starts_at' => $shift['start']->toIso8601String(),
                    'ends_at' => $shift['end']->toIso8601String(),
                    'unpaid_break_minutes' => $shift['unpaid_break_minutes'],
                    'scheduled_minutes' => (int) $shift['start']->diffInMinutes($shift['end']) - $shift['unpaid_break_minutes'],
                ], $shifts),
                'scheduled_minutes' => array_sum(array_map(
                    fn ($shift) => (int) $shift['start']->diffInMinutes($shift['end']) - $shift['unpaid_break_minutes'],
                    $shifts
                )),
            ];
        }

        return $result;
    }

    private function ensureNoOverlap(EmployeeSchedule $assignment, $existing)
    {
        $from = $assignment->effective_from->toDateString();
        $to = $assignment->effective_to?->toDateString();

        foreach ($existing as $other) {
            $otherFrom = $other->effective_from->toDateString();
            $otherTo = $other->effective_to?->toDateString();
            if (($to === null || $otherFrom <= $to) && ($otherTo === null || $otherTo >= $from)) {
                throw ValidationException::withMessages(['employee_ids' => "Employee {$assignment->employee_id} already has a schedule in this date range. End the existing assignment before adding a new one."]);
            }

            // Dates may be distinct while an overnight shift crosses the boundary.
            [$earlier, $later] = $from < $otherFrom ? [$assignment, $other] : [$other, $assignment];
            if ($earlier->effective_to->diffInDays($later->effective_from) > 3) {
                continue;
            }

            foreach ($this->boundaryShifts($earlier, false) as $left) {
                foreach ($this->boundaryShifts($later, true) as $right) {
                    if ($left['start']->lessThan($right['end']) && $right['start']->lessThan($left['end'])) {
                        throw ValidationException::withMessages(['schedule_id' => "Employee {$assignment->employee_id} has an overlapping shift at the assignment boundary."]);
                    }
                }
            }
        }
    }

    private function boundaryShifts(EmployeeSchedule $assignment, bool $atStart): array
    {
        $shifts = [];
        $boundary = $atStart ? $assignment->effective_from : $assignment->effective_to;
        for ($offset = 0; $offset < 3; $offset++) {
            $date = $atStart ? $boundary->addDays($offset) : $boundary->subDays($offset);
            if ($date < $assignment->effective_from || ($assignment->effective_to && $date > $assignment->effective_to)) {
                continue;
            }
            array_push($shifts, ...$this->shiftsForDate($assignment->schedule, $date));
        }

        return $shifts;
    }

    private function shiftsForDate(Schedule $schedule, CarbonImmutable $date): array
    {
        $shifts = [];
        foreach ($schedule->details->where('day_of_week', $date->isoWeekday()) as $detail) {
            if ($detail->is_rest_day) {
                continue;
            }

            $start = CarbonImmutable::parse($date->toDateString().' '.$detail->start_time, $schedule->timezone);
            $end = CarbonImmutable::parse($date->toDateString().' '.$detail->end_time, $schedule->timezone);
            $shifts[] = [
                'detail_id' => $detail->id,
                'start' => $start,
                'end' => $detail->ends_next_day ? $end->addDay() : $end,
                'unpaid_break_minutes' => $detail->unpaid_break_minutes,
            ];
        }

        return $shifts;
    }
}
