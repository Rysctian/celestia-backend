<?php

namespace App\Queries\Setup;

trait OfficeQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'code', 'name', 'type', 'sort_order', 'is_active', 'created_at'];
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
            ->when($request->type, fn ($query, $type) => $query->where('type', $type))
            ->when($request->filled('parent_id'), fn ($query) => $query->where('parent_id', $request->integer('parent_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($sortBy, $sortOrder)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', 'asc'))
            ->limit($limit)
            ->offset($offset);
    }
}
