<?php

namespace App\Queries;

trait AttendanceQuery
{
    public function scopeFilterSearch($query, $request, string $dateColumn = 'date')
    {
        $sortBy = in_array($request->sort_by, ['id', 'employee_id', $dateColumn, 'created_at'], true) ? $request->sort_by : $dateColumn;

        return $query->where('employee_id', $request->employee_id)
            ->when($request->date_from, fn ($query, $date) => $query->whereDate($dateColumn, '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate($dateColumn, '<=', $date))
            ->when($request->status && $dateColumn !== 'logged_at', fn ($query) => $query->where('status', $request->status))
            ->orderBy($sortBy, $request->sort_order === 'desc' ? 'desc' : 'asc')->orderBy('id')
            ->limit(min(max((int) ($request->limit ?? 15), 1), 100))->offset(max((int) ($request->offset ?? 0), 0));
    }
}
