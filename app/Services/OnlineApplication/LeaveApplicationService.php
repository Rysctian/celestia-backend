<?php

namespace App\Services\OnlineApplication;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OnlineApplication\EmployeeLeaveCredit;
use App\Models\OnlineApplication\EmployeeLeaveCreditLog;
use App\Models\OnlineApplication\LeaveApplication;
use App\Models\OnlineApplication\LeaveApplicationDetail;
use App\Models\OnlineApplication\LeavePolicy;
use App\Models\OnlineApplication\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LeaveApplicationService
{
    public function __construct(
        private ApplicationApprovalService $approvals,
        private LeavePolicyService $policies,
    ) {}

    public function findAll(Request $request, User $actor)
    {
        return $this->visibleApplications($actor)
            ->with([
                'employee:id,employee_id,first_name,last_name',
                'leaveType.leavePolicy',
                'details',
                'approvals.approver:id,employee_id,first_name,last_name',
            ])
            ->filterSearch($request)
            ->get();
    }

    public function findById(int $id, User $actor): LeaveApplication
    {
        return $this->visibleApplications($actor)
            ->with([
                'employee:id,employee_id,first_name,last_name',
                'leaveType.leavePolicy',
                'details',
                'approvals.approver:id,employee_id,first_name,last_name',
            ])
            ->findOrFail($id);
    }

    public function getBalances(string $employeeId, int $year, User $actor): array
    {
        if (! $this->isAdministrator($actor)) {
            abort_unless($actor->employee_id && $employeeId === $actor->employee_id, 403, 'View only your own leave balances.');
        }

        $leaveTypes = LeaveType::with('leavePolicy')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $credits = EmployeeLeaveCredit::query()
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        return $leaveTypes->map(function (LeaveType $leaveType) use ($credits, $year) {
            $credit = $credits->get($leaveType->id);
            $effectivePolicy = $this->policies->resolveForLeaveType($leaveType);

            return [
                'leave_type' => $leaveType,
                'policy' => $effectivePolicy,
                'year' => $year,
                'has_credit_record' => (bool) $credit,
                'allocated_credits' => $credit ? (float) $credit->allocated_credits : 0.0,
                'used_credits' => $credit ? (float) $credit->used_credits : 0.0,
                'pending_credits' => $credit ? (float) $credit->pending_credits : 0.0,
                'available_credits' => $credit ? $credit->available_credits : 0.0,
            ];
        })->values()->all();
    }

    public function downloadAttachment(int $id, User $actor)
    {
        $application = $this->findById($id, $actor);

        if (! $application->attachment_path || ! Storage::disk('local')->exists($application->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return Storage::disk('local')->download(
            $application->attachment_path,
            $application->attachment_name ?: basename($application->attachment_path),
        );
    }

    public function submit(array $data, User $actor, ?UploadedFile $attachment = null): LeaveApplication
    {
        $actorEmployeeId = $actor->employee_id;
        $isAdmin = $this->isAdministrator($actor);

        $employeeId = ! empty($data['employee_id'])
            ? (string) $data['employee_id']
            : ($actorEmployeeId ? (string) $actorEmployeeId : '');

        abort_if($employeeId === '', 422, 'Select an employee for the leave application.');

        if (! $isAdmin) {
            abort_unless($actorEmployeeId && $employeeId === $actorEmployeeId, 403, 'Submit only your own leave application.');
        }

        $allowApprover = $isAdmin
            ? (isset($data['allow_approver']) ? (bool) $data['allow_approver'] : ! (bool) ($data['auto_approved'] ?? false))
            : true;
        $status = $allowApprover ? 'pending' : 'approved';

        $storedPath = null;

        try {
            return DB::transaction(function () use ($data, $employeeId, $allowApprover, $status, $actor, $attachment, &$storedPath) {
                Employee::where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();

                $leaveType = LeaveType::with('leavePolicy')->findOrFail((int) $data['leave_type_id']);
                if (! $leaveType->is_active) {
                    throw ValidationException::withMessages([
                        'leave_type_id' => 'The selected leave type is currently inactive.',
                    ]);
                }

                $policy = $this->policies->resolveForLeaveType($leaveType);

                if ($attachment && $policy && ! $policy->allow_attachments) {
                    throw ValidationException::withMessages([
                        'attachment' => "Attachments are disabled under {$policy->name}.",
                    ]);
                }

                if (! $attachment && $policy && $policy->allow_attachments && $policy->require_attachment) {
                    throw ValidationException::withMessages([
                        'attachment' => "Supporting attachment is required under {$policy->name}.",
                    ]);
                }

                $preparedDetails = $this->prepareDetails($employeeId, $policy, $data['details']);

                $dates = array_column($preparedDetails, 'date');
                $totalDays = round(array_sum(array_column($preparedDetails, 'day_value')), 2);

                $attachmentName = null;
                if ($attachment) {
                    $storedPath = $attachment->store("leave-attachments/{$employeeId}", 'local');
                    $attachmentName = $attachment->getClientOriginalName();
                }

                $application = LeaveApplication::create([
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveType->id,
                    'date_from' => min($dates),
                    'date_to' => max($dates),
                    'total_days' => $totalDays,
                    'reason' => $data['reason'],
                    'attachment_path' => $storedPath,
                    'attachment_name' => $attachmentName,
                    'status' => $status,
                    'submitted_at' => now(),
                ]);
                $application->details()->createMany($preparedDetails);

                if ($leaveType->requires_credits) {
                    $this->applyCreditsOnSubmit(
                        $employeeId,
                        $leaveType,
                        $policy,
                        $preparedDetails,
                        $allowApprover,
                        $application->id,
                        $actor->employee_id,
                    );
                }

                if ($allowApprover) {
                    $this->approvals->snapshot($application);
                }

                return $application->load([
                    'employee:id,employee_id,first_name,last_name',
                    'leaveType.leavePolicy',
                    'details',
                    'approvals.approver:id,employee_id,first_name,last_name',
                ]);
            }, 3);
        } catch (\Throwable $e) {
            if ($storedPath && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $e;
        }
    }

    public function decide(int $id, User $actor, string $decision, ?string $remarks = null): LeaveApplication
    {
        return DB::transaction(function () use ($id, $actor, $decision, $remarks) {
            $application = LeaveApplication::with(['leaveType', 'details'])
                ->lockForUpdate()
                ->findOrFail($id);
            $previousStatus = $application->status;

            $updated = $this->approvals->decide(
                $application,
                $actor->employee_id,
                $decision,
                $remarks,
                $this->isAdministrator($actor),
            );

            if ($application->leaveType && $application->leaveType->requires_credits) {
                $this->syncCreditsOnDecision($application, $previousStatus, $updated->status, $actor->employee_id);
            }

            return $updated->load([
                'employee:id,employee_id,first_name,last_name',
                'leaveType.leavePolicy',
                'details',
                'approvals.approver:id,employee_id,first_name,last_name',
            ]);
        }, 3);
    }

    public function cancel(int $id, User $actor, ?string $remarks = null): LeaveApplication
    {
        return DB::transaction(function () use ($id, $actor, $remarks) {
            $application = LeaveApplication::with(['leaveType', 'details'])
                ->lockForUpdate()
                ->findOrFail($id);

            $updated = $this->approvals->cancel($application, $actor->employee_id, $remarks);

            if ($application->leaveType && $application->leaveType->requires_credits) {
                $this->syncCreditsOnDecision($application, 'pending', 'cancelled', $actor->employee_id);
            }

            return $updated->load([
                'employee:id,employee_id,first_name,last_name',
                'leaveType.leavePolicy',
                'details',
                'approvals.approver:id,employee_id,first_name,last_name',
            ]);
        }, 3);
    }

    public function isAdministrator(User $actor): bool
    {
        return ($actor->role && $actor->role->code === 'admin')
            || $actor->hasMenuAccess('user_management', 'view')
            || $actor->hasMenuAccess('approver_setup', 'view')
            || $actor->hasMenuAccess('leave_application', 'delete');
    }

    private function visibleApplications(User $actor)
    {
        if ($this->isAdministrator($actor)) {
            return LeaveApplication::query();
        }

        return LeaveApplication::where(fn ($query) => $query->where('employee_id', $actor->employee_id)
            ->orWhereHas('approvals', fn ($query) => $query->where('approver_id', $actor->employee_id)));
    }

    private function prepareDetails(string $employeeId, ?LeavePolicy $policy, array $details): array
    {
        $prepared = [];
        $seenDates = [];
        $today = CarbonImmutable::today();

        $dates = array_values(array_unique(array_column($details, 'date')));
        $existingDetails = LeaveApplicationDetail::query()
            ->whereIn('date', $dates)
            ->whereHas('application', fn ($query) => $query
                ->where('employee_id', $employeeId)
                ->whereIn('status', ['pending', 'approved']))
            ->get()
            ->groupBy(fn ($item) => $item->date->format('Y-m-d'));

        $totalDays = 0.0;

        foreach ($details as $index => $detail) {
            $date = $detail['date'];
            $durationType = $detail['duration_type'];
            $parsedDate = CarbonImmutable::parse($date)->startOfDay();

            if (isset($seenDates[$date])) {
                throw ValidationException::withMessages([
                    "details.$index.date" => 'Duplicate dates are not allowed in the same leave application.',
                ]);
            }
            $seenDates[$date] = true;

            if ($policy) {
                if (! $policy->allow_half_day && $durationType !== 'whole_day') {
                    throw ValidationException::withMessages([
                        "details.$index.duration_type" => "The {$policy->name} policy only allows whole-day leave.",
                    ]);
                }

                if (! $policy->allow_backdated_filing && $parsedDate->lessThan($today)) {
                    throw ValidationException::withMessages([
                        "details.$index.date" => "Backdated leave filing is not allowed under {$policy->name}.",
                    ]);
                }

                if ($policy->allow_backdated_filing && $policy->max_backdated_days !== null) {
                    $earliestAllowed = $today->subDays($policy->max_backdated_days);
                    if ($parsedDate->lessThan($earliestAllowed)) {
                        throw ValidationException::withMessages([
                            "details.$index.date" => "Leave date cannot be more than {$policy->max_backdated_days} day(s) in the past under {$policy->name}.",
                        ]);
                    }
                }

                if ($policy->min_notice_days > 0) {
                    $minDate = $today->addDays($policy->min_notice_days);
                    if ($parsedDate->lessThan($minDate)) {
                        throw ValidationException::withMessages([
                            "details.$index.date" => "Requires at least {$policy->min_notice_days} day(s) advance notice under {$policy->name}.",
                        ]);
                    }
                }

                if (! $policy->count_rest_days && $this->isEmployeeRestDay($employeeId, $date)) {
                    throw ValidationException::withMessages([
                        "details.$index.date" => "Cannot file leave on {$date} because it is a scheduled rest day.",
                    ]);
                }
            }

            foreach ($existingDetails->get($date, collect()) as $existing) {
                if ($existing->duration_type === 'whole_day'
                    || $durationType === 'whole_day'
                    || $existing->duration_type === $durationType) {
                    throw ValidationException::withMessages([
                        "details.$index.date" => "Leave on {$date} conflicts with an existing pending or approved leave.",
                    ]);
                }
            }

            $dayValue = $durationType === 'whole_day' ? 1.00 : 0.50;
            $totalDays = round($totalDays + $dayValue, 2);

            $prepared[] = [
                'date' => $date,
                'duration_type' => $durationType,
                'day_value' => $dayValue,
            ];
        }

        if ($policy && $policy->max_consecutive_days !== null && $totalDays > $policy->max_consecutive_days) {
            throw ValidationException::withMessages([
                'details' => "Total leave days ({$totalDays}) exceeds the maximum allowed ({$policy->max_consecutive_days}) under {$policy->name}.",
            ]);
        }

        return $prepared;
    }

    private function isEmployeeRestDay(string $employeeId, string $date): bool
    {
        $day = CarbonImmutable::parse($date)->dayOfWeekIso;
        $employeeSchedule = EmployeeSchedule::with([
            'schedule.details' => fn ($query) => $query->where('day_of_week', $day),
        ])->forDate($employeeId, $date)->first();

        if (! $employeeSchedule || ! $employeeSchedule->schedule) {
            return false;
        }

        $details = $employeeSchedule->schedule->details;
        if ($details->isEmpty()) {
            return false;
        }

        return $details->every(fn ($detail) => (bool) $detail->is_rest_day);
    }

    private function applyCreditsOnSubmit(
        string $employeeId,
        LeaveType $leaveType,
        ?LeavePolicy $policy,
        array $details,
        bool $reserveAsPending,
        int $applicationId,
        ?string $actedBy,
    ): void {
        $allowNegative = (bool) ($policy?->allow_negative_balance ?? false);
        $daysByYear = [];

        foreach ($details as $detail) {
            $year = CarbonImmutable::parse($detail['date'])->year;
            $daysByYear[$year] = round(($daysByYear[$year] ?? 0.0) + (float) $detail['day_value'], 2);
        }

        foreach ($daysByYear as $year => $days) {
            $credit = EmployeeLeaveCredit::query()
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $credit) {
                if (! $allowNegative) {
                    throw ValidationException::withMessages([
                        'leave_type_id' => "No {$leaveType->name} credits allocated for {$year}.",
                    ]);
                }

                $credit = EmployeeLeaveCredit::create([
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveType->id,
                    'year' => $year,
                    'allocated_credits' => 0,
                    'used_credits' => 0,
                    'pending_credits' => 0,
                ]);
            }

            if (! $allowNegative && $credit->available_credits < $days) {
                throw ValidationException::withMessages([
                    'details' => "Insufficient {$leaveType->name} credits for {$year}. Available: {$credit->available_credits}, Requested: {$days}.",
                ]);
            }

            if ($reserveAsPending) {
                $credit->update([
                    'pending_credits' => round((float) $credit->pending_credits + $days, 2),
                ]);
                $changeType = 'reserve';
                $remarks = "Reserved for pending {$leaveType->code} application #{$applicationId}.";
            } else {
                $credit->update([
                    'used_credits' => round((float) $credit->used_credits + $days, 2),
                ]);
                $changeType = 'deduct';
                $remarks = "Deducted for auto-approved {$leaveType->code} application #{$applicationId}.";
            }

            EmployeeLeaveCreditLog::create([
                'employee_leave_credit_id' => $credit->id,
                'change_type' => $changeType,
                'amount' => -$days,
                'balance_after' => $credit->fresh()->available_credits,
                'leave_application_id' => $applicationId,
                'remarks' => $remarks,
                'acted_by' => $actedBy,
            ]);
        }
    }

    private function syncCreditsOnDecision(
        LeaveApplication $application,
        string $previousStatus,
        string $nextStatus,
        ?string $actedBy,
    ): void {
        if ($previousStatus === $nextStatus) {
            return;
        }

        $daysByYear = [];
        foreach ($application->details as $detail) {
            $year = $detail->date->year;
            $daysByYear[$year] = round(($daysByYear[$year] ?? 0.0) + (float) $detail->day_value, 2);
        }

        foreach ($daysByYear as $year => $days) {
            $credit = EmployeeLeaveCredit::query()
                ->where('employee_id', $application->employee_id)
                ->where('leave_type_id', $application->leave_type_id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $credit) {
                continue;
            }

            if ($previousStatus === 'pending' && $nextStatus === 'approved') {
                $credit->update([
                    'pending_credits' => max(0, round((float) $credit->pending_credits - $days, 2)),
                    'used_credits' => round((float) $credit->used_credits + $days, 2),
                ]);
                $changeType = 'deduct';
                $logAmount = 0.0;
                $remarks = "Confirmed deduction on approval of leave application #{$application->id}.";
            } elseif ($previousStatus === 'pending' && in_array($nextStatus, ['disapproved', 'cancelled'], true)) {
                $credit->update([
                    'pending_credits' => max(0, round((float) $credit->pending_credits - $days, 2)),
                ]);
                $changeType = 'release';
                $logAmount = $days;
                $remarks = "Released reserved credits ({$nextStatus}) for leave application #{$application->id}.";
            } elseif ($previousStatus === 'approved' && $nextStatus === 'disapproved') {
                $credit->update([
                    'used_credits' => max(0, round((float) $credit->used_credits - $days, 2)),
                ]);
                $changeType = 'restore';
                $logAmount = $days;
                $remarks = "Restored credits after disapproval of leave application #{$application->id}.";
            } else {
                continue;
            }

            EmployeeLeaveCreditLog::create([
                'employee_leave_credit_id' => $credit->id,
                'change_type' => $changeType,
                'amount' => $logAmount,
                'balance_after' => $credit->fresh()->available_credits,
                'leave_application_id' => $application->id,
                'remarks' => $remarks,
                'acted_by' => $actedBy,
            ]);
        }
    }
}
