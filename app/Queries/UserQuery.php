<?php

namespace App\Queries;

trait UserQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'employee_id', 'name', 'email', 'role_id', 'created_at'];

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
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            })
            ->when($request->employee_id, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->role_id, fn ($query, $roleId) => $query->where('role_id', $roleId))
            ->when($request->email, fn ($query, $email) => $query->where('email', $email))
            ->when($request->date_from, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($request->date_to, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($sortBy, $sortOrder)
            ->limit($limit)
            ->offset($offset);
    }
}
