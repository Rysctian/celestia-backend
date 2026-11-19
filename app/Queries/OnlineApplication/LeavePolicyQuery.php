<?php

namespace App\Queries\OnlineApplication;

trait LeavePolicyQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $sorts = ['id', 'name', 'min_notice_days', 'accrual_mode', 'is_default', 'is_active', 'created_at'];
        $sortBy = in_array($request->sort_by, $sorts, true) ? $request->sort_by : 'id';
        $direction = $request->sort_order === 'asc' ? 'asc' : 'desc';

        return $query
            ->when($request->filled('accrual_mode'), fn ($q) => $q->where('accrual_mode', $request->accrual_mode))
            ->when($request->has('is_default') && $request->is_default !== '', fn ($q) => $q->where('is_default', filter_var($request->is_default, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->has('is_active') && $request->is_active !== '', fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->search, fn ($q, $search) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderBy($sortBy, $direction)
            ->when($sortBy !== 'id', fn ($q) => $q->orderBy('id', $direction))
            ->limit(min(max((int) ($request->limit ?? 15), 1), 100))
            ->offset(max((int) ($request->offset ?? 0), 0));
    }
}
