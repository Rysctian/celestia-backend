<?php

namespace App\Services\OnlineApplication;

use App\Helpers\AttendanceDuration;
use App\Models\Employee;
use App\Models\OnlineApplication\OfficialBusinessApplication;
use App\Models\OnlineApplication\OfficialBusinessApplicationDetail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficialBusinessApplicationService
{
    public function __construct(private ApplicationApprovalService $approvals) {}

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

    public function submit(array $data, User $actor)
    {
        $actorEmployeeId = $actor->employee_id;
        $isAdmin = $this->isAdministrator($actor);

        $employeeIds = [];
        if (! empty($data['employee_ids']) && is_array($data['employee_ids'])) {
            $employeeIds = array_values(array_unique(array_filter($data['employee_ids'])));
        } elseif (! empty($data['employee_id'])) {
            $employeeIds = [$data['employee_id']];
        } elseif ($actorEmployeeId) {
            $employeeIds = [$actorEmployeeId];
        }

        abort_if(empty($employeeIds), 422, 'Select at least one employee for the official business application.');

        if (! $isAdmin) {
            foreach ($employeeIds as $id) {
                abort_unless($actorEmployeeId && $id === $actorEmployeeId, 403, 'Submit only your own official business application.');
            }
        }

        $allowApprover = isset($data['allow_approver'])
            ? (bool) $data['allow_approver']
            : (! (bool) ($data['auto_approved'] ?? false));
        $status = $allowApprover ? 'pending' : 'approved';
        $destination = isset($data['destination']) ? trim((string) $data['destination']) : '';

        return DB::transaction(function () use ($data, $employeeIds, $allowApprover, $status, $destination) {
            $applications = [];

            foreach ($employeeIds as $employeeId) {
                Employee::where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();
                $details = $this->prepareDetails($employeeId, $data['details']);

                $application = OfficialBusinessApplication::create([
                    'employee_id' => $employeeId,
                    'ob_type' => $data['ob_type'],
                    'destination' => $destination !== '' ? $destination : null,
                    'reason' => $data['reason'],
                    'status' => $status,
                    'submitted_at' => now(),
                ]);
                $application->details()->createMany($details);

                if ($allowApprover) {
                    $this->approvals->snapshot($application);
                }

                $applications[] = $application->load([
                    'employee:id,employee_id,first_name,last_name',
                    'details',
                    'approvals.approver:id,employee_id,first_name,last_name',
                ]);
            }

            return count($applications) === 1 ? $applications[0] : $applications;
        }, 3);
    }

    public function decide(int $id, User $actor, string $decision, ?string $remarks = null)
    {
        $application = OfficialBusinessApplication::findOrFail($id);

        return $this->approvals->decide(
            $application,
            $actor->employee_id,
            $decision,
            $remarks,
            $this->isAdministrator($actor),
        )->load(['employee:id,employee_id,first_name,last_name', 'details', 'approvals.approver:id,employee_id,first_name,last_name']);
    }

    public function cancel(int $id, User $actor, ?string $remarks = null)
    {
        $application = OfficialBusinessApplication::findOrFail($id);

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
            return OfficialBusinessApplication::query();
        }

        return OfficialBusinessApplication::where(fn ($query) => $query->where('employee_id', $actor->employee_id)
            ->orWhereHas('approvals', fn ($query) => $query->where('approver_id', $actor->employee_id)));
    }

    private function prepareDetails(string $employeeId, array $details): array
    {
        $prepared = [];
        $intervals = [];

        $dates = array_values(array_unique(array_column($details, 'date')));
        $existingDetails = OfficialBusinessApplicationDetail::query()
            ->whereIn('date', $dates)
            ->whereHas('application', fn ($query) => $query
                ->where('employee_id', $employeeId)
                ->whereIn('status', ['pending', 'approved']))
            ->get()
            ->groupBy(fn ($item) => $item->date->format('Y-m-d'));

        foreach ($details as $index => $detail) {
            $date = $detail['date'];
            $start = CarbonImmutable::parse($date.' '.$detail['start_time']);
            $end = CarbonImmutable::parse($date.' '.$detail['end_time']);

            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages(["details.$index.end_time" => 'End time must be after start time on the same date.']);
            }

            foreach ($intervals as [$otherStart, $otherEnd]) {
                if (AttendanceDuration::overlap($start, $end, $otherStart, $otherEnd) > 0) {
                    throw ValidationException::withMessages(["details.$index.start_time" => 'Official business periods in this application must not overlap.']);
                }
            }

            foreach ($existingDetails->get($date, collect()) as $existing) {
                $existingStart = CarbonImmutable::parse($date.' '.$existing->start_time);
                $existingEnd = CarbonImmutable::parse($date.' '.$existing->end_time);
                if (AttendanceDuration::overlap($start, $end, $existingStart, $existingEnd) > 0) {
                    throw ValidationException::withMessages(["details.$index.start_time" => 'Official business period overlaps an existing pending or approved application.']);
                }
            }

            $intervals[] = [$start, $end];
            $prepared[] = [
                'date' => $date,
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
            ];
        }

        return $prepared;
    }
}
