<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Helpers\ScheduleHelper;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeScheduleService
{
    public function findAll(string $employee_id, ?Request $request = null)
    {
        return EmployeeSchedule::with('schedule.details')->where('employee_id', $employee_id)
            ->filterSearch($request ?? new Request)->get();
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
                $existing = EmployeeSchedule::where('employee_id', $employee->employee_id)->lockForUpdate()->get();

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
            $assignment = EmployeeSchedule::with('schedule')->lockForUpdate()->findOrFail($id);
            $today = CarbonImmutable::today($assignment->schedule->timezone)->toDateString();

            if (($assignment->effective_to && $assignment->effective_to->toDateString() < $today) || $data['effective_to'] < $today || $data['effective_to'] < $assignment->effective_from->toDateString()) {
                throw ValidationException::withMessages(['effective_to' => 'Only current or future assignments can be changed. The end date must be today or later and on or after the start date.']);
            }

            $assignment->effective_to = $data['effective_to'];
            $existing = EmployeeSchedule::where('employee_id', $assignment->employee_id)
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
        $dates = DateHelper::dateRange($data['from'], $data['to']);
        foreach ($dates as $dateString) {
            $assignment = $assignments->first(fn ($item) => $item->effective_from->toDateString() <= $dateString
                && (! $item->effective_to || $item->effective_to->toDateString() >= $dateString));

            if (! $assignment) {
                $result[] = ['work_date' => $dateString, 'status' => 'unassigned'];

                continue;
            }

            $shifts = ScheduleHelper::shiftsForDate($assignment->schedule, CarbonImmutable::parse($dateString));
            $result[] = [
                'work_date' => $dateString,
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

            // Adjacent assignments can still overlap when the earlier shift crosses midnight.
            if ($otherTo && CarbonImmutable::parse($otherTo)->addDay()->toDateString() === $from) {
                $this->ensureBoundaryShiftsDoNotOverlap($other, $otherTo, $assignment, $from);
            }
            if ($to && CarbonImmutable::parse($to)->addDay()->toDateString() === $otherFrom) {
                $this->ensureBoundaryShiftsDoNotOverlap($assignment, $to, $other, $otherFrom);
            }
        }
    }

    private function ensureBoundaryShiftsDoNotOverlap(EmployeeSchedule $earlier, string $earlierDate, EmployeeSchedule $later, string $laterDate): void
    {
        $earlierShifts = ScheduleHelper::shiftsForDate($earlier->schedule->loadMissing('details'), CarbonImmutable::parse($earlierDate));
        $laterShifts = ScheduleHelper::shiftsForDate($later->schedule->loadMissing('details'), CarbonImmutable::parse($laterDate));
        foreach ($earlierShifts as $first) {
            foreach ($laterShifts as $second) {
                if ($first['start']->lessThan($second['end']) && $first['end']->greaterThan($second['start'])) {
                    throw ValidationException::withMessages(['employee_ids' => 'An overnight shift overlaps the adjacent schedule assignment.']);
                }
            }
        }
    }
}
