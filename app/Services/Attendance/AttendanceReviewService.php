<?php

namespace App\Services\Attendance;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceException;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\OvertimeApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceReviewService
{
    public function __construct(private AttendanceProcessor $processor, private AttendanceCutoffService $cutoffs) {}

    public function correct(int $attendanceId, array $data, int $userId): EmployeeAttendance
    {
        $employeeId = EmployeeAttendance::findOrFail($attendanceId)->employee_id;

        return DB::transaction(function () use ($attendanceId, $data, $userId, $employeeId) {
            Employee::where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();
            $row = EmployeeAttendance::lockForUpdate()->findOrFail($attendanceId);
            $this->cutoffs->ensureDateIsOpen($row->employee_id, $row->date->toDateString());
            $in = isset($data['time_in']) ? CarbonImmutable::parse($data['time_in'])->utc() : null;
            $out = isset($data['time_out']) ? CarbonImmutable::parse($data['time_out'])->utc() : null;
            if (($in && $out && $out->lessThanOrEqualTo($in))
                || ($in && ($in->lessThan($row->sched_start->subSeconds(config('attendance.arrival_window_seconds'))) || $in->greaterThanOrEqualTo($row->sched_end)))
                || ($out && ($out->lessThanOrEqualTo($row->sched_start) || $out->greaterThan($row->sched_end->addSeconds(config('attendance.departure_window_seconds')))))) {
                throw ValidationException::withMessages(['time_in' => 'Correction times must be in order and within this schedule block and its matching windows.']);
            }
            $overlap = EmployeeAttendance::where('employee_id', $row->employee_id)->whereKeyNot($row->id)
                ->whereNotNull('time_in')->whereNotNull('time_out');
            if ($in && $out && $overlap->where('time_in', '<', $out->toDateTimeString())->where('time_out', '>', $in->toDateTimeString())->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['time_in' => 'Correction overlaps attendance from another block.']);
            }
            AttendanceCorrection::create([
                'employee_attendance_id' => $row->id, 'time_in' => $in, 'time_out' => $out,
                'reason' => $data['reason'], 'approved_by' => $userId, 'approved_at' => now(),
            ]);
            $this->processor->reprocessAttendance($row->employee_id, $row->date->toDateString());

            return $row->fresh()->load('corrections');
        });
    }

    public function approveException(array $data, int $userId): AttendanceException
    {
        return DB::transaction(function () use ($data, $userId) {
            Employee::where('employee_id', $data['employee_id'])->lockForUpdate()->firstOrFail();
            $this->cutoffs->ensureDateIsOpen($data['employee_id'], $data['date']);
            $exception = AttendanceException::updateOrCreate(['employee_id' => $data['employee_id'], 'date' => $data['date']], [
                'type' => $data['type'], 'reason' => $data['reason'], 'approved_by' => $userId, 'approved_at' => now(),
            ]);
            $this->processor->reprocessAttendance($data['employee_id'], $data['date']);

            return $exception;
        });
    }

    public function approveOvertime(int $id, int $userId): OvertimeApplication
    {
        $employeeId = OvertimeApplication::findOrFail($id)->employee_id;

        return DB::transaction(function () use ($id, $userId, $employeeId) {
            Employee::where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();
            $application = OvertimeApplication::lockForUpdate()->findOrFail($id);
            $this->cutoffs->ensureDateIsOpen($application->employee_id, $application->overtime_date->toDateString());
            if (! in_array($application->status, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages(['overtime' => 'Only pending overtime applications can be approved.']);
            }
            if ($application->status !== 'approved') {
                $application->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $userId]);
            }
            $this->processor->reprocessAttendance($application->employee_id, $application->overtime_date->toDateString());

            return $application;
        });
    }
}
