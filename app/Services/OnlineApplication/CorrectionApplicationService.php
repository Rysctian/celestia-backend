<?php

namespace App\Services\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\CorrectionApplication;
use App\Models\OnlineApplication\CorrectionApplicationDetail;
use App\Models\User;
use App\Services\Attendance\EmployeeCorrectionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectionApplicationService
{
    public function __construct(
        private ApplicationApprovalService $approvals,
        private EmployeeCorrectionService $correctionLogs,
    ) {}

    public function findAll(Request $request, User $actor)
    {
        return $this->visibleApplications($actor)
            ->with(['employee:id,employee_id,first_name,last_name', 'details', 'approvals.approver:id,employee_id,first_name,last_name'])
            ->filterSearch($request)
            ->get();
    }

    public function findById(int $id, User $actor)
    {
        return $this->visibleApplications($actor)
            ->with(['employee:id,employee_id,first_name,last_name', 'details', 'approvals.approver:id,employee_id,first_name,last_name'])
            ->findOrFail($id);
    }

    public function getLogsForDate(string $employeeId, string $date, User $actor): array
    {
        if (! $this->isAdministrator($actor)) {
            abort_unless($actor->employee_id && $employeeId === $actor->employee_id, 403, 'View only your own attendance logs.');
        }

        return $this->correctionLogs->getDateLogsSummary($employeeId, $date);
    }

    public function submit(array $data, User $actor)
    {
        $actorEmployeeId = $actor->employee_id;
        $isAdmin = $this->isAdministrator($actor);

        $employeeId = ! empty($data['employee_id'])
            ? (string) $data['employee_id']
            : ($actorEmployeeId ? (string) $actorEmployeeId : '');

        abort_if($employeeId === '', 422, 'Select an employee for the correction application.');

        if (! $isAdmin) {
            abort_unless($actorEmployeeId && $employeeId === $actorEmployeeId, 403, 'Submit only your own correction application.');
        }

        $allowApprover = isset($data['allow_approver'])
            ? (bool) $data['allow_approver']
            : (! (bool) ($data['auto_approved'] ?? false));
        $status = $allowApprover ? 'pending' : 'approved';

        return DB::transaction(function () use ($data, $employeeId, $allowApprover, $status) {
            Employee::where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();
            $details = $this->prepareDetails($employeeId, $data['details']);

            $application = CorrectionApplication::create([
                'employee_id' => $employeeId,
                'reason' => $data['reason'],
                'status' => $status,
                'submitted_at' => now(),
            ]);
            $application->details()->createMany($details);

            if ($allowApprover) {
                $this->approvals->snapshot($application);
            } else {
                $this->correctionLogs->syncApprovedLogs($application);
            }

            return $application->load([
                'employee:id,employee_id,first_name,last_name',
                'details',
                'approvals.approver:id,employee_id,first_name,last_name',
            ]);
        }, 3);
    }

    public function decide(int $id, User $actor, string $decision, ?string $remarks = null)
    {
        return DB::transaction(function () use ($id, $actor, $decision, $remarks) {
            $application = CorrectionApplication::lockForUpdate()->findOrFail($id);
            $wasApproved = $application->status === 'approved';

            $updated = $this->approvals->decide(
                $application,
                $actor->employee_id,
                $decision,
                $remarks,
                $this->isAdministrator($actor),
            );

            if (! $wasApproved && $updated->status === 'approved') {
                $this->correctionLogs->syncApprovedLogs($updated);
            } elseif ($wasApproved && $updated->status === 'disapproved') {
                $this->correctionLogs->removeDisapprovedLogs($updated);
            }

            return $updated->load(['employee:id,employee_id,first_name,last_name', 'details', 'approvals.approver:id,employee_id,first_name,last_name']);
        }, 3);
    }

    public function cancel(int $id, User $actor, ?string $remarks = null)
    {
        $application = CorrectionApplication::findOrFail($id);

        return $this->approvals->cancel($application, $actor->employee_id, $remarks)
            ->load(['employee:id,employee_id,first_name,last_name', 'details', 'approvals.approver:id,employee_id,first_name,last_name']);
    }

    private function isAdministrator(User $actor): bool
    {
        return ($actor->role && $actor->role->code === 'admin')
            || $actor->hasMenuAccess('user_management', 'view')
            || $actor->hasMenuAccess('approver_setup', 'view');
    }

    private function visibleApplications(User $actor)
    {
        if ($this->isAdministrator($actor)) {
            return CorrectionApplication::query();
        }

        return CorrectionApplication::where(fn ($query) => $query->where('employee_id', $actor->employee_id)
            ->orWhereHas('approvals', fn ($query) => $query->where('approver_id', $actor->employee_id)));
    }

    private function prepareDetails(string $employeeId, array $details): array
    {
        $prepared = [];
        $seenDates = [];

        $dates = array_values(array_unique(array_column($details, 'date')));
        $existingDates = CorrectionApplicationDetail::query()
            ->whereIn('date', $dates)
            ->whereHas('application', fn ($query) => $query
                ->where('employee_id', $employeeId)
                ->whereIn('status', ['pending', 'approved']))
            ->get()
            ->map(fn ($item) => $item->date->format('Y-m-d'))
            ->flip();

        foreach ($details as $index => $detail) {
            $date = $detail['date'];

            if (isset($seenDates[$date])) {
                throw ValidationException::withMessages(["details.$index.date" => 'Duplicate dates are not allowed in the same correction application.']);
            }
            $seenDates[$date] = true;

            if ($existingDates->has($date)) {
                throw ValidationException::withMessages(["details.$index.date" => "A pending or approved correction application already exists for {$date}."]);
            }

            $amIn = ! empty($detail['am_in']) ? CarbonImmutable::parse($date.' '.$detail['am_in']) : null;
            $amOut = ! empty($detail['am_out']) ? CarbonImmutable::parse($date.' '.$detail['am_out']) : null;
            $pmIn = ! empty($detail['pm_in']) ? CarbonImmutable::parse($date.' '.$detail['pm_in']) : null;
            $pmOut = ! empty($detail['pm_out']) ? CarbonImmutable::parse($date.' '.$detail['pm_out']) : null;

            if (! $amIn && ! $amOut && ! $pmIn && ! $pmOut) {
                throw ValidationException::withMessages(["details.$index.am_in" => 'Provide at least one AM or PM log time for each date.']);
            }

            if ($amIn && $amOut && $amOut->lessThanOrEqualTo($amIn)) {
                throw ValidationException::withMessages(["details.$index.am_out" => 'AM Out must be later than AM In.']);
            }

            if ($pmIn && $pmOut && $pmOut->lessThanOrEqualTo($pmIn)) {
                throw ValidationException::withMessages(["details.$index.pm_out" => 'PM Out must be later than PM In.']);
            }

            $lastAm = $amOut ?? $amIn;
            $firstPm = $pmIn ?? $pmOut;
            if ($lastAm && $firstPm && $firstPm->lessThan($lastAm)) {
                throw ValidationException::withMessages(["details.$index.pm_in" => 'PM times must not be earlier than AM times.']);
            }

            $oldSummary = $this->correctionLogs->getDateLogsSummary($employeeId, $date);
            $oldPaired = $oldSummary['paired_db'];

            $prepared[] = [
                'date' => $date,
                'old_am_in' => $oldPaired['am_in'],
                'old_am_out' => $oldPaired['am_out'],
                'old_pm_in' => $oldPaired['pm_in'],
                'old_pm_out' => $oldPaired['pm_out'],
                'am_in' => $amIn?->format('H:i:s'),
                'am_out' => $amOut?->format('H:i:s'),
                'pm_in' => $pmIn?->format('H:i:s'),
                'pm_out' => $pmOut?->format('H:i:s'),
            ];
        }

        return $prepared;
    }
}
