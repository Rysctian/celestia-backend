<?php

namespace App\Queries;

trait AttendancePolicyQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = [
            'id',
            'name',
            'tardy_grace_seconds',
            'tardy_count_from',
            'absent_after_late_seconds',
            'undertime_grace_seconds',
            'is_default',
            'is_active',
            'created_at',
        ];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'id';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->tardy_count_from, fn ($query, $mode) => $query->where('tardy_count_from', $mode))
            ->when($request->filled('is_default'), fn ($query) => $query->where('is_default', $request->boolean('is_default')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderBy($sortBy, $sortOrder)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', $sortOrder))
            ->limit($limit)
            ->offset($offset);
    }
}
