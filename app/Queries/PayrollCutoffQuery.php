<?php

namespace App\Queries;

trait PayrollCutoffQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'schedule_type', 'quarter', 'start_date', 'end_date', 'is_active', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'start_date';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->schedule_type, fn ($query, $type) => $query->where('schedule_type', $type))
            ->when($request->filled('quarter'), fn ($query) => $query->where('quarter', $request->quarter))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('end_date', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('start_date', '<=', $date))
            ->orderBy($sortBy, $sortOrder)
            ->limit($limit)
            ->offset($offset);
    }
}
