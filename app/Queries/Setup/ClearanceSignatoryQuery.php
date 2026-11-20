<?php

namespace App\Queries\Setup;

trait ClearanceSignatoryQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'clearance_type_id', 'sign_order', 'signatory_label', 'is_required', 'is_active', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'sign_order';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('signatory_label', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('clearance_type_id'), fn ($query) => $query->where('clearance_type_id', $request->integer('clearance_type_id')))
            ->when($request->filled('clearance_requirement_id'), fn ($query) => $query->where('clearance_requirement_id', $request->integer('clearance_requirement_id')))
            ->when($request->filled('office_id'), fn ($query) => $query->where('office_id', $request->integer('office_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('position_id'), fn ($query) => $query->where('position_id', $request->integer('position_id')))
            ->when($request->filled('is_required'), fn ($query) => $query->where('is_required', $request->boolean('is_required')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($sortBy, $sortOrder)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', 'asc'))
            ->limit($limit)
            ->offset($offset);
    }
}
