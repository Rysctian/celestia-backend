<?php

namespace App\Services\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\ApplicationApproval;
use App\Models\OnlineApplication\ApproverSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationApprovalService
{
    public function snapshot(Model $application)
    {
        return DB::transaction(function () use ($application) {
            // Setup replacement uses the same employee lock, including an empty setup.
            Employee::where('employee_id', $application->employee_id)->lockForUpdate()->firstOrFail();
            $application = $application->newQuery()->lockForUpdate()->findOrFail($application->getKey());
            $this->ensurePending($application);
            $type = $application->getMorphClass();
            if (! in_array($type, ApproverSetup::APPLICATION_TYPES, true)) {
                throw ValidationException::withMessages(['application_type' => 'Register the application model in the morph map before submitting.']);
            }
            if ($this->approvals($application)->exists()) {
                throw ValidationException::withMessages(['approvals' => 'This application already has an approval snapshot.']);
            }
            $setup = ApproverSetup::where('employee_id', $application->employee_id)
                ->where('application_type', $type)->orderBy('sequence')->lockForUpdate()->get();
            if ($setup->isEmpty() || $setup->pluck('sequence')->all() !== range(1, $setup->count())) {
                throw ValidationException::withMessages(['approvers' => 'Configure at least one consecutive approver before submitting.']);
            }

            foreach ($setup as $approver) {
                $this->approvals($application)->create([
                    'employee_id' => $application->employee_id, 'application_type' => $type,
                    'application_id' => $application->getKey(), 'approver_id' => $approver->approver_id,
                    'sequence' => $approver->sequence, 'status' => 'pending',
                ]);
            }

            return $this->approvals($application)->orderBy('sequence')->get();
        }, 3);
    }

    public function decide(Model $application, string $actorEmployeeId, string $decision, ?string $remarks = null)
    {
        return DB::transaction(function () use ($application, $actorEmployeeId, $decision, $remarks) {
            $application = $application->newQuery()->lockForUpdate()->findOrFail($application->getKey());
            $this->ensurePending($application);
            if (! in_array($decision, ['approved', 'disapproved'], true)) {
                throw ValidationException::withMessages(['status' => 'Choose approved or disapproved.']);
            }
            $approvals = $this->approvals($application)->orderBy('sequence')->lockForUpdate()->get();
            $current = $approvals->firstWhere('status', 'pending');
            abort_unless($current && $current->approver_id === $actorEmployeeId, 403, 'Only the current approver can act.');
            $previous = $approvals->where('sequence', '<', $current->sequence);
            if ($previous->contains(fn ($approval) => $approval->status !== 'approved')) {
                throw ValidationException::withMessages(['approvals' => 'Previous sequences must all be approved.']);
            }
            $current->update(['status' => $decision, 'remarks' => $remarks, 'acted_at' => now()]);
            if ($decision === 'disapproved' || ! $this->approvals($application)->where('status', '!=', 'approved')->exists()) {
                $application->update(['status' => $decision]);
            }

            return $application;
        }, 3);
    }

    public function cancel(Model $application, string $actorEmployeeId, ?string $remarks = null)
    {
        return DB::transaction(function () use ($application, $actorEmployeeId, $remarks) {
            $application = $application->newQuery()->lockForUpdate()->findOrFail($application->getKey());
            abort_unless($application->employee_id === $actorEmployeeId, 403, 'Only the applicant can cancel.');
            $this->ensurePending($application);
            $pending = $this->approvals($application)->where('status', 'pending')->orderBy('sequence')->lockForUpdate()->get();
            foreach ($pending as $approval) {
                $approval->update(['status' => 'cancelled', 'remarks' => $remarks, 'acted_at' => now()]);
            }
            $application->update(['status' => 'cancelled']);

            return $application;
        }, 3);
    }

    private function approvals(Model $application)
    {
        return ApplicationApproval::where('application_type', $application->getMorphClass())->where('application_id', $application->getKey());
    }

    private function ensurePending(Model $application): void
    {
        if ($application->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending applications can be acted on.']);
        }
    }
}
