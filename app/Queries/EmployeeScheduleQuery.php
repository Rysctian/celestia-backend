<?php

namespace App\Queries;

use Illuminate\Support\Arr;

trait EmployeeScheduleQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'schedule_id', 'effective_from', 'effective_to', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts) ? $request->sort_by : 'id';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $limit = min(max((int) ($request->limit ?? 15), 1), 100);
        $offset = max((int) ($request->offset ?? 0), 0);
        $today = now()->toDateString();
        $currentStates = array_values(array_unique(array_map(
            fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            Arr::wrap($request->input('is_current')),
        )));

        return $query
            ->when($request->search, fn ($q, $search) => $q->whereHas('schedule', fn ($schedule) => $schedule->where('name', 'like', "%{$search}%")))
            ->when($request->schedule_id, fn ($q, $id) => $q->whereIn('schedule_id', Arr::wrap($id)))
            ->when($request->filled('is_current') && count($currentStates) === 1, function ($q) use ($currentStates, $today) {
                if ($currentStates[0]) {
                    $q->whereDate('effective_from', '<=', $today)
                        ->where(fn ($end) => $end->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today));
                } else {
                    $q->where(fn ($history) => $history->whereDate('effective_from', '>', $today)
                        ->orWhereDate('effective_to', '<', $today));
                }
            })
            ->when($request->date_from, fn ($q, $from) => $q->whereDate('effective_from', '>=', $from))
            ->when($request->date_to, fn ($q, $to) => $q->whereDate('effective_from', '<=', $to))
            ->orderBy($sortBy, $sortOrder)
            ->when($sortBy !== 'id', fn ($q) => $q->orderBy('id'))
            ->limit($limit)->offset($offset);
    }
}
