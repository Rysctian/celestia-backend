<?php

namespace App\Queries;

trait CutOffQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'schedule_type', 'name', 'year', 'month', 'is_active', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'id';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->schedule_type, fn ($query, $type) => $query->where('schedule_type', $type))
            ->when($request->filled('year'), fn ($query) => $query->where('year', $request->year))
            ->when($request->filled('month'), fn ($query) => $query->where('month', $request->month))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderBy($sortBy, $sortOrder)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', $sortOrder))
            ->limit($limit)->offset($offset);
    }
}
