<?php

namespace App\Queries;

use App\Models\Setup\SetupReferenceItem;
use Illuminate\Support\Arr;

trait EmployeeQuery
{
    public function scopeFilterSearch($query, $request)
    {
        $allowedSorts = ['id', 'employee_id', 'first_name', 'last_name', 'date_hired', 'created_at'];

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
                        ->orWhere('personal_email', 'like', "%{$search}%")
                        ->orWhere('work_email', 'like', "%{$search}%");
                });
            })
            ->when($request->type, fn ($query, $type) => $this->applySetupMatchedFilter($query, 'type', 'employment_type', $type))
            ->when($request->gender, fn ($query, $gender) => $this->applySetupMatchedFilter($query, 'gender', 'gender', $gender))
            ->when($request->civil_status, fn ($query, $civilStatus) => $this->applySetupMatchedFilter($query, 'civil_status', 'civil_status', $civilStatus))
            ->when($request->employment_status, fn ($query, $status) => $this->applySetupMatchedFilter($query, 'employment_status', 'employment_status', $status))
            ->when($request->work_arrangement, fn ($query, $arr) => $this->applySetupMatchedFilter($query, 'work_arrangement', 'work_arrangement', $arr))
            ->when($request->employee_category, fn ($query, $cat) => $this->applySetupMatchedFilter($query, 'employee_category', 'employee_category', $cat))
            ->when($request->filled('office_id'), fn ($query) => $query->where('office_id', $request->integer('office_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('position_id'), fn ($query) => $query->where('position_id', $request->integer('position_id')))
            ->when($request->employee_id, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->date_from, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($request->date_to, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($sortBy, $sortOrder)
            ->limit($limit)
            ->offset($offset);
    }

    /**
     * Matches case-insensitively and expands setup codes/names so both setup-coded
     * values and legacy display strings filter consistently.
     */
    private function applySetupMatchedFilter($query, string $column, string $category, mixed $rawValue): void
    {
        $values = array_values(array_filter(array_map(
            static fn ($item) => strtolower(trim((string) $item)),
            Arr::wrap($rawValue),
        )));

        if ($values === []) {
            return;
        }

        $matchedItems = SetupReferenceItem::query()
            ->where('category', $category)
            ->get(['code', 'name']);

        $expanded = $values;
        foreach ($matchedItems as $item) {
            $code = strtolower($item->code);
            $name = strtolower($item->name);
            if (in_array($code, $values, true) || in_array($name, $values, true)) {
                $expanded[] = $code;
                $expanded[] = $name;
                $expanded[] = str_replace('_', '-', $code);
            }
        }

        $uniqueValues = array_values(array_unique($expanded));
        $placeholders = implode(', ', array_fill(0, count($uniqueValues), '?'));

        $query->whereRaw("LOWER({$column}) IN ({$placeholders})", $uniqueValues);
    }
}
