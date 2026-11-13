<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendancePolicyService
{
    public function search($request)
    {
        return AttendancePolicy::query()->filterSearch($request)->get();
    }

    public function findById(string $id): ?AttendancePolicy
    {
        return AttendancePolicy::find($id);
    }

    public function createPolicy(array $data): AttendancePolicy
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->normalizeDefaults($data);
            $this->assertConsistentState($payload);

            if ($payload['is_default']) {
                AttendancePolicy::where('is_default', true)->lockForUpdate()->update(['is_default' => false]);
            }

            return AttendancePolicy::create($payload)->fresh();
        });
    }

    public function updatePolicy(array $data, string $id): AttendancePolicy
    {
        return DB::transaction(function () use ($data, $id) {
            $policy = AttendancePolicy::lockForUpdate()->findOrFail($id);

            $merged = [
                'tardy_grace_seconds' => $data['tardy_grace_seconds'] ?? $policy->tardy_grace_seconds,
                'absent_after_late_seconds' => array_key_exists('absent_after_late_seconds', $data)
                    ? $data['absent_after_late_seconds']
                    : $policy->absent_after_late_seconds,
                'is_default' => $data['is_default'] ?? $policy->is_default,
                'is_active' => $data['is_active'] ?? $policy->is_active,
            ];

            $this->assertConsistentState($merged);

            // Prevent leaving the system with a deactivated sole default policy.
            if ($policy->is_default && ! $merged['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => 'Assign another default attendance policy before deactivating the current default.',
                ]);
            }

            if ($merged['is_default']) {
                AttendancePolicy::where('id', '!=', $policy->id)
                    ->where('is_default', true)
                    ->lockForUpdate()
                    ->update(['is_default' => false]);
            }

            $policy->update($data);

            return $policy->fresh();
        });
    }

    public function deletePolicy(string $id): void
    {
        DB::transaction(function () use ($id) {
            $policy = AttendancePolicy::lockForUpdate()->findOrFail($id);

            if ($policy->schedules()->exists()) {
                throw ValidationException::withMessages([
                    'attendance_policy' => 'This attendance policy is assigned to one or more schedules.',
                ]);
            }

            if ($policy->is_default) {
                throw ValidationException::withMessages([
                    'attendance_policy' => 'Assign another default attendance policy before deleting the current default.',
                ]);
            }

            $policy->delete();
        });
    }

    /**
     * Resolves the effective policy for a schedule, falling back to the active system default.
     */
    public function resolveForSchedule(?Schedule $schedule): ?AttendancePolicy
    {
        if ($schedule?->attendance_policy_id) {
            return $schedule->relationLoaded('attendancePolicy')
                ? $schedule->attendancePolicy
                : $schedule->attendancePolicy()->first();
        }

        return AttendancePolicy::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    private function normalizeDefaults(array $data): array
    {
        return [
            ...$data,
            'early_in_window_seconds' => $data['early_in_window_seconds'] ?? 14400,
            'late_out_window_seconds' => $data['late_out_window_seconds'] ?? 14400,
            'duplicate_tap_window_seconds' => $data['duplicate_tap_window_seconds'] ?? 120,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function assertConsistentState(array $state): void
    {
        if ($state['is_default'] && ! $state['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'Default attendance policy must be active.',
            ]);
        }

        if ($state['absent_after_late_seconds'] !== null && (int) $state['absent_after_late_seconds'] <= (int) $state['tardy_grace_seconds']) {
            throw ValidationException::withMessages([
                'absent_after_late_seconds' => 'Absent threshold must be greater than the tardy grace period.',
            ]);
        }
    }
}
