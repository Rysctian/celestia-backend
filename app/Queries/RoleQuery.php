<?php

namespace App\Queries;

trait RoleQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'code', 'name', 'created_at'];

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
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->code, fn ($query, $code) => $query->where('code', $code))
            ->when($request->name, fn ($query, $name) => $query->where('name', $name))
            ->when($request->date_from, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($request->date_to, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($sortBy, $sortOrder)
            ->limit($limit)
            ->offset($offset);
    }
}
