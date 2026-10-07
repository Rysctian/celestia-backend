<?php

namespace App\Queries;

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

        return $query
            ->when($request->search, fn ($q, $search) => $q->whereHas('schedule', fn ($schedule) =>
                $schedule->where('name', 'like', "%{$search}%")))
            ->when($request->schedule_id, fn ($q, $id) => $q->where('schedule_id', $id))
            ->when($request->filled('is_current'), function ($q) use ($request, $today) {
                if ($request->boolean('is_current')) {
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
