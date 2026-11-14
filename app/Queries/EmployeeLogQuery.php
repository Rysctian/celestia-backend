<?php

namespace App\Queries;

use Illuminate\Support\Arr;

trait EmployeeLogQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'employee_id', 'logged_at', 'source', 'device_id', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'logged_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);

        return $query
            ->when($request->search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('employee_id', 'like', "%{$search}%")
                        ->orWhere('device_id', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->employee_id, fn ($query, $employeeId) => $query->whereIn('employee_id', Arr::wrap($employeeId)))
            ->when($request->source, fn ($query, $source) => $query->whereIn('source', Arr::wrap($source)))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('logged_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('logged_at', '<=', $date))
            ->orderBy($sortBy, $sortOrder)
            ->when($sortBy !== 'id', fn ($query) => $query->orderBy('id', $sortOrder))
            ->limit($limit)
            ->offset($offset);
    }
}
