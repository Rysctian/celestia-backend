<?php

namespace App\Queries\OnlineApplication;

use Illuminate\Support\Arr;

trait LeaveApplicationQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $sorts = ['id', 'employee_id', 'leave_type_id', 'date_from', 'date_to', 'total_days', 'status', 'submitted_at', 'created_at'];
        $sortBy = in_array($request->sort_by, $sorts, true) ? $request->sort_by : 'id';
        $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';

        return $query
            ->when($request->filled('employee_id'), fn ($q) => $q->whereIn('employee_id', Arr::wrap($request->employee_id)))
            ->when($request->filled('leave_type_id'), fn ($q) => $q->whereIn('leave_type_id', Arr::wrap($request->leave_type_id)))
            ->when($request->filled('status'), fn ($q) => $q->whereIn('status', Arr::wrap($request->status)))
            ->when($request->search, fn ($q, $search) => $q->where(fn ($sub) => $sub
                ->where('reason', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhereHas('leaveType', fn ($lt) => $lt->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                ->orWhereHas('employee', fn ($emp) => $emp->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                ->orWhereHas('approvals.approver', fn ($app) => $app->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
            ))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('submitted_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('submitted_at', '<=', $date))
            ->orderBy($sortBy, $direction)
            ->when($sortBy !== 'id', fn ($q) => $q->orderBy('id', $direction))
            ->limit(min(max((int) ($request->limit ?? 15), 1), 100))
            ->offset(max((int) ($request->offset ?? 0), 0));
    }
}
