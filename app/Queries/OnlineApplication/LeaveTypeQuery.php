<?php

namespace App\Queries\OnlineApplication;

trait LeaveTypeQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $sorts = ['id', 'code', 'name', 'default_credits', 'is_paid', 'requires_credits', 'is_active', 'created_at'];
        $sortBy = in_array($request->sort_by, $sorts, true) ? $request->sort_by : 'id';
        $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';

        return $query
            ->when($request->has('is_active') && $request->is_active !== '', fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->has('is_paid') && $request->is_paid !== '', fn ($q) => $q->where('is_paid', filter_var($request->is_paid, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('leave_policy_id'), fn ($q) => $q->where('leave_policy_id', (int) $request->leave_policy_id))
            ->when($request->search, fn ($q, $search) => $q->where(fn ($sub) => $sub
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
            ))
            ->orderBy($sortBy, $direction)
            ->when($sortBy !== 'id', fn ($q) => $q->orderBy('id', $direction))
            ->limit(min(max((int) ($request->limit ?? 50), 1), 100))
            ->offset(max((int) ($request->offset ?? 0), 0));
    }
}
