<?php

namespace App\Queries\OnlineApplication;

use Illuminate\Support\Arr;

trait EmployeeLeaveCreditQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $sorts = ['id', 'employee_id', 'leave_type_id', 'year', 'allocated_credits', 'used_credits', 'pending_credits', 'created_at'];
        $sortBy = in_array($request->sort_by, $sorts, true) ? $request->sort_by : 'id';
        $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';

        return $query
            ->when($request->filled('employee_id'), fn ($q) => $q->whereIn('employee_id', Arr::wrap($request->employee_id)))
            ->when($request->filled('leave_type_id'), fn ($q) => $q->whereIn('leave_type_id', Arr::wrap($request->leave_type_id)))
            ->when($request->filled('year'), fn ($q) => $q->where('year', (int) $request->year))
            ->when($request->search, fn ($q, $search) => $q->where(fn ($sub) => $sub
                ->where('employee_id', 'like', "%{$search}%")
                ->orWhereHas('employee', fn ($emp) => $emp->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                ->orWhereHas('leaveType', fn ($lt) => $lt->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
            ))
            ->orderBy($sortBy, $direction)
            ->when($sortBy !== 'id', fn ($q) => $q->orderBy('id', $direction))
            ->limit(min(max((int) ($request->limit ?? 15), 1), 100))
            ->offset(max((int) ($request->offset ?? 0), 0));
    }
}
