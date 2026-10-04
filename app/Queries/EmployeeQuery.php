<?php

namespace App\Queries;

trait EmployeeQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'employee_id', 'first_name', 'last_name', 'created_at'];

        $sortBy = in_array($request->sort_by, $allowedSorts)
            ? $request->sort_by
            : 'created_at';

        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('personal_email', 'like', "%{$search}%");
                });
            })
            ->when($request->type, fn ($query, $type) => $query->where('type', $type))
            ->when($request->gender, fn ($query, $gender) => $query->where('gender', $gender))
            ->when($request->civil_status, fn ($query, $civilStatus) => $query->where('civil_status', $civilStatus))
            ->when($request->employee_id, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->date_from, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($request->date_to, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($sortBy, $sortOrder)
            ->limit($limit)
            ->offset($offset);
    }
}
