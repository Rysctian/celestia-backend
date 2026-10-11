<?php

namespace App\Queries\OnlineApplication;

trait OvertimeApplicationQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $sorts = ['id', 'employee_id', 'status', 'submitted_at', 'created_at'];
        $sortBy = in_array($request->sort_by, $sorts, true) ? $request->sort_by : 'id';
        $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';

        return $query
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->employee_id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->search, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('reason', 'like', "%{$search}%")->orWhere('employee_id', 'like', "%{$search}%")))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('submitted_at', '<=', $date))
            ->orderBy($sortBy, $direction)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', $direction))
            ->limit(min(max((int) ($request->limit ?? 15), 1), 100))
            ->offset(max((int) ($request->offset ?? 0), 0));
    }
}
