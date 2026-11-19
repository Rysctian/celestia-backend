<?php

namespace App\Services\OnlineApplication;

use App\Models\OnlineApplication\LeavePolicy;
use App\Models\OnlineApplication\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeavePolicyService
{
    public function findAll(Request $request)
    {
        return LeavePolicy::withCount('leaveTypes')
            ->filterSearch($request)
            ->get();
    }

    public function findById(int $id): LeavePolicy
    {
        return LeavePolicy::withCount('leaveTypes')->findOrFail($id);
    }

    public function create(array $data): LeavePolicy
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['is_default'])) {
                LeavePolicy::where('is_default', true)->lockForUpdate()->update(['is_default' => false]);
            }

            $policy = LeavePolicy::create($data);

            return $policy->loadCount('leaveTypes');
        }, 3);
    }

    public function update(int $id, array $data): LeavePolicy
    {
        return DB::transaction(function () use ($id, $data) {
            $policy = LeavePolicy::lockForUpdate()->findOrFail($id);

            $nextIsDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $policy->is_default;
            $nextIsActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $policy->is_active;

            if ($nextIsDefault && ! $nextIsActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'The default leave policy must remain active.',
                ]);
            }

            if ($nextIsDefault) {
                LeavePolicy::where('id', '!=', $policy->id)
                    ->where('is_default', true)
                    ->lockForUpdate()
                    ->update(['is_default' => false]);
            }

            $policy->update($data);

            return $policy->fresh()->loadCount('leaveTypes');
        }, 3);
    }

    public function destroy(int $id): void
    {
        DB::transaction(function () use ($id) {
            $policy = LeavePolicy::lockForUpdate()->findOrFail($id);

            if ($policy->leaveTypes()->exists()) {
                throw ValidationException::withMessages([
                    'leave_policy' => 'This leave policy is assigned to one or more leave types.',
                ]);
            }

            $policy->delete();
        }, 3);
    }

    public function resolveForLeaveType(?LeaveType $leaveType): ?LeavePolicy
    {
        if ($leaveType?->leavePolicy && $leaveType->leavePolicy->is_active) {
            return $leaveType->leavePolicy;
        }

        return LeavePolicy::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
