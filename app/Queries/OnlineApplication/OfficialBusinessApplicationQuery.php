<?php

namespace App\Queries\OnlineApplication;

use Illuminate\Support\Arr;

trait OfficialBusinessApplicationQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $sorts = ['id', 'employee_id', 'ob_type', 'destination', 'status', 'submitted_at', 'created_at'];
        $sortBy = in_array($request->sort_by, $sorts, true) ? $request->sort_by : 'id';
        $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';

        return $query
            ->when($request->filled('employee_id'), fn ($query) => $query->whereIn('employee_id', Arr::wrap($request->employee_id)))
            ->when($request->filled('ob_type'), fn ($query) => $query->whereIn('ob_type', Arr::wrap($request->ob_type)))
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', Arr::wrap($request->status)))
            ->when($request->search, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('destination', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhereHas('employee', fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                ->orWhereHas('approvals.approver', fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
            ))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('submitted_at', '<=', $date))
            ->orderBy($sortBy, $direction)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', $direction))
            ->limit(min(max((int) ($request->limit ?? 15), 1), 100))
            ->offset(max((int) ($request->offset ?? 0), 0));
    }
}
