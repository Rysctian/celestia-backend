<?php

namespace App\Queries;

trait ScheduleQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'name', 'timezone', 'is_active', 'created_at'];

        $sortBy = in_array($request->sort_by, $allowedSorts)
            ? $request->sort_by
            : 'created_at';

        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('timezone', 'like', "%{$search}%");
                });
            })
            ->when($request->name, fn ($query, $name) => $query->where('name', $name))
            ->when($request->timezone, fn ($query, $timezone) => $query->where('timezone', $timezone))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->date_from, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($request->date_to, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($sortBy, $sortOrder)
            ->limit($limit)
            ->offset($offset);
    }
}
