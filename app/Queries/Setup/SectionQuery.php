<?php

namespace App\Queries\Setup;

trait SectionQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'code', 'name', 'division_id', 'department_id', 'sort_order', 'is_active', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'sort_order';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('short_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('division_id'), fn ($query) => $query->where('division_id', $request->integer('division_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($sortBy, $sortOrder)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', 'asc'))
            ->limit($limit)
            ->offset($offset);
    }
}
