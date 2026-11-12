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
        $records = ApproverSetup::with(['employee:id,employee_id,first_name,last_name', 'approver:id,employee_id,first_name,last_name'])
            ->when($request->employee_id, function ($query, $id) {
                return is_array($id) ? $query->whereIn('employee_id', $id) : $query->where('employee_id', $id);
            })
            ->when($request->application_type, function ($query, $type) {
                return is_array($type) ? $query->whereIn('application_type', $type) : $query->where('application_type', $type);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('employee_id', 'like', "%{$search}%")
                        ->orWhere('approver_id', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                        ->orWhereHas('approver', fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('employee_id')->orderBy('application_type')->orderBy('sequence')
            ->limit(min(max((int) ($request->limit ?? 15), 1), 100))
            ->offset(max((int) ($request->offset ?? 0), 0))->get();

        if ($records->isNotEmpty()) {
            $allSteps = ApproverSetup::with('approver:id,employee_id,first_name,last_name')
                ->whereIn('employee_id', $records->pluck('employee_id')->unique())
                ->whereIn('application_type', $records->pluck('application_type')->unique())
                ->orderBy('sequence')
                ->get()
                ->groupBy(fn ($item) => $item->employee_id . '|' . $item->application_type);

            foreach ($records as $record) {
                $key = $record->employee_id . '|' . $record->application_type;
                $record->setRelation('steps', $allSteps->get($key, collect([$record]))->values());
            }
        }

        return $records;
    }

    public function findSetup(string $employeeId, string $type)
    {
        Employee::where('employee_id', $employeeId)->firstOrFail();

        return ApproverSetup::with(['employee:id,employee_id,first_name,last_name', 'approver:id,employee_id,first_name,last_name'])
            ->where('employee_id', $employeeId)->where('application_type', $type)->orderBy('sequence')->get();
    }

    public function saveSetup(array $data)
    {
        return DB::transaction(function () use ($data) {
            $employeeIds = !empty($data['employee_ids'])
                ? array_values(array_unique((array) $data['employee_ids']))
                : (array) ($data['employee_id'] ?? []);

            if (empty($employeeIds)) {
                throw ValidationException::withMessages(['employee_ids' => 'Select at least one employee.']);
            }

            Employee::whereIn('employee_id', $employeeIds)->lockForUpdate()->get();
            if (Employee::whereIn('employee_id', $employeeIds)->count() !== count($employeeIds)) {
                throw ValidationException::withMessages(['employee_ids' => 'One or more selected employees do not exist.']);
            }

            $approvers = collect($data['approvers'])->sortBy('sequence')->values();
            $sequences = $approvers->pluck('sequence')->map(fn ($sequence) => (int) $sequence)->all();
            if (! in_array($data['application_type'], ApproverSetup::APPLICATION_TYPES, true)
                || $approvers->isEmpty() || $sequences !== range(1, $approvers->count())) {
                throw ValidationException::withMessages(['approvers' => 'Select an application type and consecutive approvers starting at sequence 1.']);
            }
            if (Employee::whereIn('employee_id', $approvers->pluck('approver_id')->unique())->count() !== $approvers->pluck('approver_id')->unique()->count()) {
                throw ValidationException::withMessages(['approvers' => 'One or more approvers no longer exist.']);
            }

            ApproverSetup::whereIn('employee_id', $employeeIds)->where('application_type', $data['application_type'])->delete();
            $records = [];
            foreach ($employeeIds as $empId) {
                foreach ($approvers as $approver) {
                    $records[] = [
                        'employee_id' => $empId,
                        'application_type' => $data['application_type'],
                        'approver_id' => $approver['approver_id'],
                        'sequence' => $approver['sequence'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            ApproverSetup::insert($records);

            return $this->findSetup($employeeIds[0], $data['application_type']);
        }, 3);
    }
}
