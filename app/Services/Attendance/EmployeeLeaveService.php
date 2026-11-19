<?php

namespace App\Services\Attendance;

use App\Models\OnlineApplication\LeaveApplicationDetail;

class EmployeeLeaveService
{
    public function getApprovedLeaveForDate(string $employeeId, string $date)
    {
        return LeaveApplicationDetail::with(['application.leaveType'])
            ->whereDate('date', $date)
            ->whereHas('application', fn ($query) => $query
                ->where('employee_id', $employeeId)
                ->where('status', 'approved'))
            ->get();
    }
}
