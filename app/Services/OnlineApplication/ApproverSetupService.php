<?php

namespace App\Services\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\ApproverSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproverSetupService
{
    public function findAll(Request $request)
    {
        return ApproverSetup::with('approver:id,employee_id,first_name,last_name')
            ->when($request->employee_id, fn ($query, $id) => $query->where('employee_id', $id))
            ->when($request->application_type, fn ($query, $type) => $query->where('application_type', $type))
            ->orderBy('employee_id')->orderBy('application_type')->orderBy('sequence')
            ->limit(min(max((int) ($request->limit ?? 15), 1), 100))
            ->offset(max((int) ($request->offset ?? 0), 0))->get();
    }

    public function findSetup(string $employeeId, string $type)
    {
        Employee::where('employee_id', $employeeId)->firstOrFail();

        return ApproverSetup::with('approver:id,employee_id,first_name,last_name')
            ->where('employee_id', $employeeId)->where('application_type', $type)->orderBy('sequence')->get();
    }

    public function saveSetup(array $data)
    {
        return DB::transaction(function () use ($data) {
            Employee::where('employee_id', $data['employee_id'])->lockForUpdate()->firstOrFail();
            $approvers = collect($data['approvers'])->sortBy('sequence')->values();
            $sequences = $approvers->pluck('sequence')->map(fn ($sequence) => (int) $sequence)->all();
            if (! in_array($data['application_type'], ApproverSetup::APPLICATION_TYPES, true)
                || $approvers->isEmpty() || $sequences !== range(1, $approvers->count())) {
                throw ValidationException::withMessages(['approvers' => 'Select an application type and consecutive approvers starting at sequence 1.']);
            }
            if (Employee::whereIn('employee_id', $approvers->pluck('approver_id')->unique())->count() !== $approvers->pluck('approver_id')->unique()->count()) {
                throw ValidationException::withMessages(['approvers' => 'One or more approvers no longer exist.']);
            }

            ApproverSetup::where('employee_id', $data['employee_id'])->where('application_type', $data['application_type'])->delete();
            foreach ($approvers as $approver) {
                ApproverSetup::create([
                    'employee_id' => $data['employee_id'], 'application_type' => $data['application_type'],
                    'approver_id' => $approver['approver_id'], 'sequence' => $approver['sequence'],
                ]);
            }

            return $this->findSetup($data['employee_id'], $data['application_type']);
        }, 3);
    }
}
