<?php

namespace App\Services\OnlineApplication;

use App\Helpers\AttendanceDuration;
use App\Helpers\ScheduleHelper;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OnlineApplication\OvertimeApplication;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeApplicationService
{
    public function __construct(private ApplicationApprovalService $approvals) {}

    public function findAll(Request $request, User $actor)
    {
        return $this->visibleApplications($actor)->with(['details', 'approvals'])->filterSearch($request)->get();
    }

    public function findById(int $id, User $actor)
    {
        return $this->visibleApplications($actor)->with(['details', 'approvals'])->findOrFail($id);
    }

    public function submit(array $data, User $actor)
    {
        $employeeId = $actor->employee_id;
        abort_unless($employeeId && ($data['employee_id'] ?? $employeeId) === $employeeId, 403, 'Submit only your own overtime.');

        return DB::transaction(function () use ($data, $employeeId) {
            // Match schedule assignment's template-before-employee lock order.
            $scheduleIds = EmployeeSchedule::where('employee_id', $employeeId)->pluck('schedule_id');
            $schedules = Schedule::with('details')->whereIn('id', $scheduleIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            Employee::where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();
            $assignments = EmployeeSchedule::where('employee_id', $employeeId)->orderByDesc('effective_from')->lockForUpdate()->get();
            $details = $this->prepareDetails($data['details'], $assignments, $schedules);
            $application = OvertimeApplication::create([
                'employee_id' => $employeeId, 'reason' => $data['reason'], 'status' => 'pending', 'submitted_at' => now(),
            ]);
            $application->details()->createMany($details);
            $this->approvals->snapshot($application);

            return $application->load(['details', 'approvals']);
        }, 3);
    }

    public function decide(int $id, User $actor, string $decision, ?string $remarks = null)
    {
        $application = OvertimeApplication::findOrFail($id);

        return $this->approvals->decide($application, $actor->employee_id, $decision, $remarks)->load(['details', 'approvals']);
    }

    public function cancel(int $id, User $actor, ?string $remarks = null)
    {
        $application = OvertimeApplication::findOrFail($id);

        return $this->approvals->cancel($application, $actor->employee_id, $remarks)->load(['details', 'approvals']);
    }

    private function visibleApplications(User $actor)
    {
        return OvertimeApplication::where(fn ($query) => $query->where('employee_id', $actor->employee_id)
            ->orWhereHas('approvals', fn ($query) => $query->where('approver_id', $actor->employee_id)));
    }

    private function prepareDetails(array $details, $assignments, $schedules): array
    {
        $prepared = [];
        $intervals = [];
        foreach ($details as $index => $detail) {
            $date = $detail['date'];
            $assignment = $this->assignmentForDate($assignments, $date);
            $schedule = $assignment ? $schedules->get($assignment->schedule_id) : null;
            if (! $schedule || $schedule->details->where('day_of_week', CarbonImmutable::parse($date)->isoWeekday())->isEmpty()) {
                throw ValidationException::withMessages(["details.$index.date" => 'An employee schedule must be configured for this date.']);
            }
            $start = CarbonImmutable::parse($date.' '.$detail['start_time'], $schedule->timezone);
            $end = CarbonImmutable::parse($date.' '.$detail['end_time'], $schedule->timezone);
            $duration = AttendanceDuration::seconds($start, $end);
            $break = (int) ($detail['break_seconds'] ?? 0);
            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages(["details.$index.end_time" => 'End time must be after start time on the same date.']);
            }
            if ($break < 0 || $break >= $duration) {
                throw ValidationException::withMessages(["details.$index.break_seconds" => 'Break must be nonnegative and shorter than the overtime period.']);
            }

            // Include yesterday's shifts: overnight scheduled work may extend into this date.
            foreach ([$start->subDay()->toDateString(), $date] as $shiftDate) {
                $shiftAssignment = $this->assignmentForDate($assignments, $shiftDate);
                $shiftSchedule = $shiftAssignment ? $schedules->get($shiftAssignment->schedule_id) : null;
                if (! $shiftSchedule) {
                    continue;
                }
                foreach (ScheduleHelper::shiftsForDate($shiftSchedule, CarbonImmutable::parse($shiftDate)) as $shift) {
                    if (AttendanceDuration::overlap($start, $end, $shift['start'], $shift['end']) > 0) {
                        throw ValidationException::withMessages(["details.$index.start_time" => 'Overtime must not overlap scheduled work.']);
                    }
                }
            }
            foreach ($intervals as [$otherStart, $otherEnd]) {
                if (AttendanceDuration::overlap($start, $end, $otherStart, $otherEnd) > 0) {
                    throw ValidationException::withMessages(["details.$index.start_time" => 'Overtime periods in this application must not overlap.']);
                }
            }
            $intervals[] = [$start, $end];
            $prepared[] = [
                'date' => $date, 'start_time' => $start->format('H:i:s'), 'end_time' => $end->format('H:i:s'),
                'break_seconds' => $break, 'requested_seconds' => $duration - $break,
            ];
        }

        return $prepared;
    }

    private function assignmentForDate($assignments, string $date)
    {
        return $assignments->first(fn ($assignment) => $assignment->effective_from->toDateString() <= $date
            && (! $assignment->effective_to || $assignment->effective_to->toDateString() >= $date));
    }
}
