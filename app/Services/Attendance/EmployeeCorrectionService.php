<?php

namespace App\Services\Attendance;

use App\Models\EmployeeLog;
use App\Models\OnlineApplication\CorrectionApplication;
use Carbon\Carbon;

class EmployeeCorrectionService
{
    private const DEVICE_PREFIX = 'CORRECTION-APP-';

    public function getDateLogsSummary(string $employeeId, string $date): array
    {
        $logs = EmployeeLog::forDate($employeeId, $date)->get();
        $paired = $this->pairLogsToSlots($logs->map(fn ($log) => Carbon::parse($log->logged_at)->format('H:i:s'))->values()->all());

        return [
            'employee_id' => $employeeId,
            'date' => $date,
            'logs' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'logged_at' => Carbon::parse($log->logged_at)->format('Y-m-d H:i:s'),
                'time' => Carbon::parse($log->logged_at)->format('H:i'),
                'source' => $log->source,
                'device_id' => $log->device_id,
            ])->values()->all(),
            'paired' => [
                'am_in' => $paired['am_in'] ? substr($paired['am_in'], 0, 5) : null,
                'am_out' => $paired['am_out'] ? substr($paired['am_out'], 0, 5) : null,
                'pm_in' => $paired['pm_in'] ? substr($paired['pm_in'], 0, 5) : null,
                'pm_out' => $paired['pm_out'] ? substr($paired['pm_out'], 0, 5) : null,
            ],
            'paired_db' => $paired,
        ];
    }

    public function syncApprovedLogs(CorrectionApplication $application): void
    {
        $application->loadMissing('details');
        $deviceId = self::DEVICE_PREFIX.$application->id;

        foreach ($application->details as $detail) {
            $date = $detail->date instanceof \DateTimeInterface
                ? $detail->date->format('Y-m-d')
                : Carbon::parse((string) $detail->date)->format('Y-m-d');

            $oldTimestamps = [];
            $newTimestamps = [];

            foreach (['old_am_in', 'old_am_out', 'old_pm_in', 'old_pm_out'] as $slot) {
                if ($detail->{$slot}) {
                    $oldTimestamps[] = Carbon::parse("{$date} {$detail->{$slot}}")->format('Y-m-d H:i:s');
                }
            }

            foreach (['am_in', 'am_out', 'pm_in', 'pm_out'] as $slot) {
                if ($detail->{$slot}) {
                    $newTimestamps[] = Carbon::parse("{$date} {$detail->{$slot}}")->format('Y-m-d H:i:s');
                }
            }

            $oldTimestamps = array_values(array_unique($oldTimestamps));
            $newTimestamps = array_values(array_unique($newTimestamps));

            $removedTimestamps = array_values(array_diff($oldTimestamps, $newTimestamps));
            if (! empty($removedTimestamps)) {
                EmployeeLog::query()
                    ->where('employee_id', $application->employee_id)
                    ->whereIn('logged_at', $removedTimestamps)
                    ->whereDoesntHave('attendanceLogs')
                    ->delete();
            }

            if (empty($newTimestamps)) {
                continue;
            }

            $existing = EmployeeLog::query()
                ->where('employee_id', $application->employee_id)
                ->whereIn('logged_at', $newTimestamps)
                ->pluck('logged_at')
                ->map(fn ($dt) => Carbon::parse($dt)->format('Y-m-d H:i:s'))
                ->flip();

            foreach ($newTimestamps as $timestamp) {
                if ($existing->has($timestamp)) {
                    continue;
                }

                EmployeeLog::create([
                    'employee_id' => $application->employee_id,
                    'logged_at' => $timestamp,
                    'device_id' => $deviceId,
                    'source' => 'correction',
                ]);
            }
        }
    }

    public function removeDisapprovedLogs(CorrectionApplication $application): void
    {
        EmployeeLog::query()
            ->where('employee_id', $application->employee_id)
            ->where('source', 'correction')
            ->where('device_id', self::DEVICE_PREFIX.$application->id)
            ->whereDoesntHave('attendanceLogs')
            ->delete();
    }

    private function pairLogsToSlots(array $times): array
    {
        $slots = [
            'am_in' => null,
            'am_out' => null,
            'pm_in' => null,
            'pm_out' => null,
        ];

        $count = count($times);
        if ($count === 0) {
            return $slots;
        }

        if ($count >= 4) {
            return [
                'am_in' => $times[0],
                'am_out' => $times[1],
                'pm_in' => $times[$count - 2],
                'pm_out' => $times[$count - 1],
            ];
        }

        if ($count === 1) {
            if ($times[0] < '12:00:00') {
                $slots['am_in'] = $times[0];
            } elseif ($times[0] < '15:00:00') {
                $slots['pm_in'] = $times[0];
            } else {
                $slots['pm_out'] = $times[0];
            }

            return $slots;
        }

        if ($count === 2) {
            if ($times[0] < '12:00:00' && $times[1] <= '13:00:00') {
                $slots['am_in'] = $times[0];
                $slots['am_out'] = $times[1];
            } elseif ($times[0] < '12:00:00') {
                $slots['am_in'] = $times[0];
                $slots['pm_out'] = $times[1];
            } else {
                $slots['pm_in'] = $times[0];
                $slots['pm_out'] = $times[1];
            }

            return $slots;
        }

        // count === 3
        if ($times[0] < '12:00:00') {
            $slots['am_in'] = $times[0];
            if ($times[1] <= '12:30:00') {
                $slots['am_out'] = $times[1];
                $slots['pm_out'] = $times[2];
            } else {
                $slots['pm_in'] = $times[1];
                $slots['pm_out'] = $times[2];
            }
        } else {
            $slots['pm_in'] = $times[0];
            $slots['pm_out'] = $times[2];
        }

        return $slots;
    }
}
