<?php

namespace App\Queries\Setup;

trait SetupReferenceItemQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'category', 'code', 'name', 'sort_order', 'is_default', 'is_active', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'sort_order';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $limit = min(max((int) ($request->limit ?? 50), 1), 200);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->filled('parent_id'), fn ($query) => $query->where('parent_id', $request->integer('parent_id')))
            ->when($request->filled('is_default'), fn ($query) => $query->where('is_default', $request->boolean('is_default')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($sortBy, $sortOrder)
            ->when($sortBy !== 'name', fn ($query) => $query->orderBy('name', 'asc'))
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', 'asc'))
            ->limit($limit)
            ->offset($offset);
    }
}
