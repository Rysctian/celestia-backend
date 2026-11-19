<?php

namespace App\Services\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\EmployeeLeaveCredit;
use App\Models\OnlineApplication\EmployeeLeaveCreditLog;
use App\Models\OnlineApplication\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveSetupService
{
    public function findAllTypes(Request $request)
    {
        return LeaveType::with('leavePolicy:id,name,is_default,is_active')
            ->filterSearch($request)
            ->get();
    }

    public function findType(int $id): LeaveType
    {
        return LeaveType::with('leavePolicy:id,name,is_default,is_active')->findOrFail($id);
    }

    public function createType(array $data): LeaveType
    {
        $data['code'] = strtoupper(trim($data['code']));

        return LeaveType::create($data)->load('leavePolicy:id,name,is_default,is_active');
    }

    public function updateType(int $id, array $data): LeaveType
    {
        $type = LeaveType::findOrFail($id);
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        $type->update($data);

        return $type->fresh()->load('leavePolicy:id,name,is_default,is_active');
    }

    public function deleteType(int $id): void
    {
        $type = LeaveType::findOrFail($id);

        if ($type->applications()->exists()) {
            throw ValidationException::withMessages([
                'leave_type' => 'Cannot delete a leave type that already has leave applications.',
            ]);
        }

        if ($type->credits()->exists()) {
            throw ValidationException::withMessages([
                'leave_type' => 'Cannot delete a leave type that has employee credit allocations.',
            ]);
        }

        $type->delete();
    }

    public function findAllCredits(Request $request)
    {
        return EmployeeLeaveCredit::with([
            'employee:id,employee_id,first_name,last_name',
            'leaveType:id,code,name,is_paid,requires_credits,default_credits',
        ])->filterSearch($request)->get();
    }

    public function upsertCredit(array $data, ?string $actedBy = null): EmployeeLeaveCredit
    {
        return DB::transaction(function () use ($data, $actedBy) {
            $credit = EmployeeLeaveCredit::query()
                ->where('employee_id', $data['employee_id'])
                ->where('leave_type_id', $data['leave_type_id'])
                ->where('year', (int) $data['year'])
                ->lockForUpdate()
                ->first();

            $newAllocated = round((float) $data['allocated_credits'], 2);

            if (! $credit) {
                $credit = EmployeeLeaveCredit::create([
                    'employee_id' => $data['employee_id'],
                    'leave_type_id' => (int) $data['leave_type_id'],
                    'year' => (int) $data['year'],
                    'allocated_credits' => $newAllocated,
                    'used_credits' => 0,
                    'pending_credits' => 0,
                ]);

                EmployeeLeaveCreditLog::create([
                    'employee_leave_credit_id' => $credit->id,
                    'change_type' => 'grant',
                    'amount' => $newAllocated,
                    'balance_after' => $credit->available_credits,
                    'remarks' => $data['remarks'] ?? 'Initial annual leave credit allocation.',
                    'acted_by' => $actedBy,
                ]);
            } else {
                $minimumRequired = round((float) $credit->used_credits + (float) $credit->pending_credits, 2);
                if ($newAllocated < $minimumRequired) {
                    throw ValidationException::withMessages([
                        'allocated_credits' => "Allocated credits cannot be less than used + pending credits ({$minimumRequired}).",
                    ]);
                }

                $diff = round($newAllocated - (float) $credit->allocated_credits, 2);
                $credit->update(['allocated_credits' => $newAllocated]);

                if ($diff !== 0.0) {
                    EmployeeLeaveCreditLog::create([
                        'employee_leave_credit_id' => $credit->id,
                        'change_type' => 'adjustment',
                        'amount' => $diff,
                        'balance_after' => $credit->fresh()->available_credits,
                        'remarks' => $data['remarks'] ?? 'Manual leave credit adjustment.',
                        'acted_by' => $actedBy,
                    ]);
                }
            }

            return $credit->fresh()->load([
                'employee:id,employee_id,first_name,last_name',
                'leaveType:id,code,name,is_paid,requires_credits,default_credits',
            ]);
        }, 3);
    }

    public function updateCredit(int $id, array $data, ?string $actedBy = null): EmployeeLeaveCredit
    {
        return DB::transaction(function () use ($id, $data, $actedBy) {
            $credit = EmployeeLeaveCredit::lockForUpdate()->findOrFail($id);
            $newAllocated = round((float) $data['allocated_credits'], 2);
            $minimumRequired = round((float) $credit->used_credits + (float) $credit->pending_credits, 2);

            if ($newAllocated < $minimumRequired) {
                throw ValidationException::withMessages([
                    'allocated_credits' => "Allocated credits cannot be less than used + pending credits ({$minimumRequired}).",
                ]);
            }

            $diff = round($newAllocated - (float) $credit->allocated_credits, 2);
            $credit->update(['allocated_credits' => $newAllocated]);

            if ($diff !== 0.0) {
                EmployeeLeaveCreditLog::create([
                    'employee_leave_credit_id' => $credit->id,
                    'change_type' => 'adjustment',
                    'amount' => $diff,
                    'balance_after' => $credit->fresh()->available_credits,
                    'remarks' => $data['remarks'] ?? 'Manual leave credit adjustment.',
                    'acted_by' => $actedBy,
                ]);
            }

            return $credit->fresh()->load([
                'employee:id,employee_id,first_name,last_name',
                'leaveType:id,code,name,is_paid,requires_credits,default_credits',
            ]);
        }, 3);
    }

    public function allocateAnnualCredits(array $data, ?string $actedBy = null): array
    {
        $year = (int) $data['year'];
        $overwrite = (bool) ($data['overwrite'] ?? false);

        $leaveTypes = LeaveType::query()
            ->where('is_active', true)
            ->where('requires_credits', true)
            ->when(! empty($data['leave_type_id']), fn ($q) => $q->where('id', (int) $data['leave_type_id']))
            ->get();

        if ($leaveTypes->isEmpty()) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'No active credit-based leave types found to allocate.',
            ]);
        }

        $employeeIds = ! empty($data['employee_ids'])
            ? array_values(array_unique($data['employee_ids']))
            : Employee::pluck('employee_id')->all();

        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($employeeIds, $leaveTypes, $year, $overwrite, $actedBy, &$createdCount, &$updatedCount) {
            foreach ($employeeIds as $employeeId) {
                foreach ($leaveTypes as $leaveType) {
                    $existing = EmployeeLeaveCredit::query()
                        ->where('employee_id', $employeeId)
                        ->where('leave_type_id', $leaveType->id)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    $targetAmount = round((float) $leaveType->default_credits, 2);

                    if (! $existing) {
                        $credit = EmployeeLeaveCredit::create([
                            'employee_id' => $employeeId,
                            'leave_type_id' => $leaveType->id,
                            'year' => $year,
                            'allocated_credits' => $targetAmount,
                            'used_credits' => 0,
                            'pending_credits' => 0,
                        ]);

                        EmployeeLeaveCreditLog::create([
                            'employee_leave_credit_id' => $credit->id,
                            'change_type' => 'grant',
                            'amount' => $targetAmount,
                            'balance_after' => $credit->available_credits,
                            'remarks' => "Annual {$year} default allocation for {$leaveType->code}.",
                            'acted_by' => $actedBy,
                        ]);
                        $createdCount++;
                    } elseif ($overwrite) {
                        $minimumRequired = round((float) $existing->used_credits + (float) $existing->pending_credits, 2);
                        $nextAllocated = max($targetAmount, $minimumRequired);
                        $diff = round($nextAllocated - (float) $existing->allocated_credits, 2);

                        if ($diff !== 0.0) {
                            $existing->update(['allocated_credits' => $nextAllocated]);
                            EmployeeLeaveCreditLog::create([
                                'employee_leave_credit_id' => $existing->id,
                                'change_type' => 'adjustment',
                                'amount' => $diff,
                                'balance_after' => $existing->fresh()->available_credits,
                                'remarks' => "Annual {$year} re-allocation for {$leaveType->code}.",
                                'acted_by' => $actedBy,
                            ]);
                            $updatedCount++;
                        }
                    }
                }
            }
        }, 3);

        return [
            'year' => $year,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
        ];
    }

    public function getCreditLogs(int $creditId)
    {
        $credit = EmployeeLeaveCredit::with([
            'employee:id,employee_id,first_name,last_name',
            'leaveType:id,code,name',
        ])->findOrFail($creditId);

        return [
            'credit' => $credit,
            'logs' => $credit->logs()->get(),
        ];
    }
}
