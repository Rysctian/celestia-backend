<?php

namespace App\Services\Attendance;

use App\Models\AttendanceException;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceProcessor
{
    public function __construct(
        private AttendanceScheduleService $schedules,
        private TimesheetProcessor $timesheets,
        private AttendanceCalculator $calculator,
        private AttendanceCutoffService $cutoffs,
    ) {}

    public function reprocessAttendance(string $employeeId, string $date, bool $refreshCutoffs = true): array
    {
        return DB::transaction(function () use ($employeeId, $date, $refreshCutoffs) {
            Employee::where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();
            $this->cutoffs->ensureDateIsOpen($employeeId, $date);
            $day = $this->schedules->forDate($employeeId, $date);
            $interpretation = $this->timesheets->process($employeeId, $date, $day);
            $sheets = $interpretation['timesheets'];
            $exception = AttendanceException::where('employee_id', $employeeId)->whereDate('date', $date)->lockForUpdate()->first();
            $attendance = collect();
            foreach ($day['blocks'] as $block) {
                $attendance->push($this->calculator->calculate($employeeId, $block, $sheets, $exception));
            }

            // Remove obsolete derived rows after assignment changes, preserving reviewed rows.
            $obsolete = EmployeeAttendance::where('employee_id', $employeeId)->whereDate('date', $date)
                ->whereNotIn('id', $attendance->pluck('id'))->get();
            foreach ($obsolete as $row) {
                if ($row->corrections()->exists()) {
                    throw ValidationException::withMessages(['date' => 'A corrected schedule block no longer exists. Review its schedule assignment first.']);
                }
                $row->delete();
            }
            // A changed schedule may move a tap from a neighboring work date.
            // Rebuild its previous owner's attendance in the same transaction.
            foreach ($interpretation['affected_dates'] as $affectedDate) {
                $this->reprocessAttendance($employeeId, $affectedDate);
            }
            if ($refreshCutoffs) {
                $this->cutoffs->refreshOpenSummaries($employeeId, $date);
            }

            return [
                'date' => $date, 'status' => $day['blocks'] ? 'working' : $day['status'],
                'attendance' => $attendance, 'timesheets' => $sheets,
            ];
        });
    }
}
