<?php

namespace App\Services\Setup;

use App\Models\Employee;
use App\Models\Setup\ClearanceType;
use App\Models\Setup\Department;
use App\Models\Setup\Division;
use App\Models\Setup\Office;
use App\Models\Setup\Position;
use App\Models\Setup\Section;
use App\Models\Setup\SetupReferenceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetupReferenceService
{
    /**
     * Maps scalar setup reference categories to the corresponding column on `employees`.
     *
     * @var array<string, string>
     */
    private const EMPLOYEE_SCALAR_CATEGORY_COLUMN = [
        'gender' => 'gender',
        'civil_status' => 'civil_status',
        'blood_type' => 'blood_type',
        'country' => 'country',
        'nationality' => 'nationality',
        'citizenship' => 'citizenship',
        'religion' => 'religion',
        'educational_attainment' => 'educational_attainment',
        'degree_course' => 'degree_course',
        'relationship_type' => 'emergency_contact_relationship',
        'disability_type' => 'disability_type',
        'employment_type' => 'type',
        'employment_status' => 'employment_status',
        'employee_category' => 'employee_category',
        'appointment_type' => 'appointment_type',
        'employment_classification' => 'employment_classification',
        'work_arrangement' => 'work_arrangement',
        'separation_type' => 'separation_type',
        'separation_reason' => 'separation_reason',
    ];
    public function listCategories(string $group): array
    {
        $catalog = SetupReferenceItem::categoriesForGroup($group);
        $counts = SetupReferenceItem::query()
            ->where('group', $group)
            ->selectRaw('category, COUNT(*) as total_count, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $result = [];

        foreach ($catalog as $key => $meta) {
            $row = $counts->get($key);
            $result[] = [
                'category' => $key,
                'group' => $meta['group'],
                'label' => $meta['label'],
                'description' => $meta['description'],
                'parent_category' => $meta['parent_category'],
                'total_count' => (int) ($row->total_count ?? 0),
                'active_count' => (int) ($row->active_count ?? 0),
            ];
        }

        return $result;
    }

    public function search(string $group, Request $request)
    {
        $items = SetupReferenceItem::query()
            ->where('group', $group)
            ->with(['parent:id,category,code,name'])
            ->withCount(['children'])
            ->filterSearch($request)
            ->get();

        return $this->attachReferenceUsageCollection($items);
    }

    public function findById(string $group, int $id): ?SetupReferenceItem
    {
        $item = SetupReferenceItem::query()
            ->where('group', $group)
            ->with(['parent:id,category,code,name'])
            ->withCount(['children'])
            ->find($id);

        if (! $item) {
            return null;
        }

        return $this->attachReferenceUsageCollection(collect([$item]))->first();
    }

    public function createItem(string $group, array $data): SetupReferenceItem
    {
        return DB::transaction(function () use ($group, $data) {
            $category = $data['category'];

            if (SetupReferenceItem::groupForCategory($category) !== $group) {
                throw ValidationException::withMessages([
                    'category' => 'The selected category does not belong to this setup group.',
                ]);
            }

            $this->validateParentRelationship($category, $data['parent_id'] ?? null);

            $isDefault = (bool) ($data['is_default'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($isDefault && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Default setup item must be active.',
                ]);
            }

            if ($isDefault) {
                SetupReferenceItem::where('category', $category)
                    ->where('is_default', true)
                    ->lockForUpdate()
                    ->update(['is_default' => false]);
            }

            $item = SetupReferenceItem::create([
                ...$data,
                'group' => $group,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_default' => $isDefault,
                'is_active' => $isActive,
            ]);

            return $item->fresh()->load(['parent:id,category,code,name']);
        });
    }

    public function updateItem(string $group, int $id, array $data): SetupReferenceItem
    {
        return DB::transaction(function () use ($group, $id, $data) {
            $item = SetupReferenceItem::where('group', $group)->lockForUpdate()->findOrFail($id);

            if (array_key_exists('parent_id', $data)) {
                $this->validateParentRelationship($item->category, $data['parent_id'], $item->id);
            }

            $isDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $item->is_default;
            $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $item->is_active;

            if ($isDefault && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Default setup item must be active.',
                ]);
            }

            if ($isDefault) {
                SetupReferenceItem::where('category', $item->category)
                    ->where('id', '!=', $item->id)
                    ->where('is_default', true)
                    ->lockForUpdate()
                    ->update(['is_default' => false]);
            }

            unset($data['group'], $data['category']);
            $item->update($data);

            return $item->fresh()->load(['parent:id,category,code,name']);
        });
    }

    public function deleteItem(string $group, int $id): void
    {
        DB::transaction(function () use ($group, $id) {
            $item = SetupReferenceItem::where('group', $group)
                ->withCount(['children'])
                ->lockForUpdate()
                ->findOrFail($id);

            $this->attachReferenceUsageCollection(collect([$item]));

            if ($item->getAttribute('is_in_use')) {
                $reason = lcfirst((string) ($item->getAttribute('usage_reason') ?: 'in use in the system'));
                throw ValidationException::withMessages([
                    'item' => "Cannot delete this setup item because it is {$reason}.",
                ]);
            }

            $item->delete();
        });
    }

    /**
     * Returns active reference options grouped by category for form dropdown hydration.
     */
    public function getActiveOptions(?array $categories = null, ?string $group = null): array
    {
        $query = SetupReferenceItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');

        if ($group !== null && in_array($group, SetupReferenceItem::GROUPS, true)) {
            $query->where('group', $group);
        }

        if ($categories !== null && $categories !== []) {
            $validCategories = array_values(array_intersect($categories, array_keys(SetupReferenceItem::CATEGORY_CATALOG)));
            $query->whereIn('category', $validCategories);
        }

        $items = $query->get(['id', 'group', 'category', 'code', 'name', 'parent_id', 'metadata', 'is_default', 'sort_order']);

        return $items
            ->groupBy('category')
            ->map(fn ($groupItems) => $groupItems->values()->all())
            ->all();
    }

    /**
     * Returns active organizational and clearance setup entities for shared filters & dropdowns.
     */
    public function getActiveEntityOptions(?array $entities = null): array
    {
        $supported = ['offices', 'departments', 'divisions', 'sections', 'positions', 'clearance_types'];
        $requested = $entities !== null && $entities !== []
            ? array_values(array_intersect($entities, $supported))
            : $supported;

        $result = [];

        foreach ($requested as $entity) {
            $result[$entity] = match ($entity) {
                'offices' => Office::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'type', 'parent_id'])
                    ->values()
                    ->all(),
                'departments' => Department::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'office_id'])
                    ->values()
                    ->all(),
                'divisions' => Division::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'department_id'])
                    ->values()
                    ->all(),
                'sections' => Section::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'division_id', 'department_id'])
                    ->values()
                    ->all(),
                'positions' => Position::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->get(['id', 'code', 'title', 'department_id', 'division_id', 'section_id'])
                    ->map(fn (Position $p) => [
                        'id' => $p->id,
                        'code' => $p->code,
                        'name' => $p->title,
                        'title' => $p->title,
                        'department_id' => $p->department_id,
                        'division_id' => $p->division_id,
                        'section_id' => $p->section_id,
                    ])
                    ->values()
                    ->all(),
                'clearance_types' => ClearanceType::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'applies_to'])
                    ->values()
                    ->all(),
            };
        }

        return $result;
    }

    private function validateParentRelationship(string $category, ?int $parentId, ?int $selfId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            throw ValidationException::withMessages([
                'parent_id' => 'A setup item cannot reference itself as its parent.',
            ]);
        }

        $expectedParentCategory = SetupReferenceItem::parentCategoryFor($category);
        if ($expectedParentCategory === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'This setup category does not support parent linking.',
            ]);
        }

        $parentCategory = SetupReferenceItem::where('id', $parentId)->value('category');
        if ($parentCategory !== $expectedParentCategory) {
            throw ValidationException::withMessages([
                'parent_id' => "Parent item must belong to the {$expectedParentCategory} category.",
            ]);
        }
    }

    /**
     * Attaches `usage_count`, `is_in_use`, and `usage_reason` to a collection of SetupReferenceItems
     * using batched queries per category (zero N+1 queries).
     *
     * @param  Collection<int, SetupReferenceItem>  $items
     * @return Collection<int, SetupReferenceItem>
     */
    private function attachReferenceUsageCollection(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $categories = $items->pluck('category')->unique()->values();
        $usageMaps = [];

        foreach ($categories as $category) {
            if (isset(self::EMPLOYEE_SCALAR_CATEGORY_COLUMN[$category])) {
                $column = self::EMPLOYEE_SCALAR_CATEGORY_COLUMN[$category];
                $usageMaps[$category] = Employee::query()
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->selectRaw("LOWER(TRIM({$column})) as normalized_value, COUNT(*) as aggregate_count")
                    ->groupByRaw("LOWER(TRIM({$column}))")
                    ->pluck('aggregate_count', 'normalized_value')
                    ->map(fn ($count) => (int) $count)
                    ->all();
            } elseif ($category === 'language') {
                $languageCounts = [];
                $rows = Employee::query()->whereNotNull('languages')->pluck('languages');

                foreach ($rows as $languages) {
                    if (! is_array($languages)) {
                        continue;
                    }
                    $seenInRow = [];
                    foreach ($languages as $lang) {
                        if (! is_string($lang)) {
                            continue;
                        }
                        $normalized = strtolower(trim($lang));
                        if ($normalized === '' || isset($seenInRow[$normalized])) {
                            continue;
                        }
                        $seenInRow[$normalized] = true;
                        $languageCounts[$normalized] = ($languageCounts[$normalized] ?? 0) + 1;
                    }
                }

                $usageMaps[$category] = $languageCounts;
            }
        }

        return $items->map(function (SetupReferenceItem $item) use ($usageMaps) {
            $childrenCount = (int) ($item->children_count ?? 0);
            $employeeCount = 0;

            if (isset($usageMaps[$item->category])) {
                $map = $usageMaps[$item->category];
                $codeKey = strtolower(trim((string) $item->code));
                $nameKey = strtolower(trim((string) $item->name));

                $employeeCount += $map[$codeKey] ?? 0;
                if ($nameKey !== '' && $nameKey !== $codeKey) {
                    $employeeCount += $map[$nameKey] ?? 0;
                }
            }

            $total = $childrenCount + $employeeCount;
            $parts = [];
            if ($childrenCount > 0) {
                $parts[] = $childrenCount === 1 ? '1 child item' : "{$childrenCount} child items";
            }
            if ($employeeCount > 0) {
                $parts[] = $employeeCount === 1 ? '1 employee' : "{$employeeCount} employees";
            }

            $item->setAttribute('usage_count', $total);
            $item->setAttribute('is_in_use', $total > 0);
            $item->setAttribute('usage_reason', $total > 0 ? 'In use by '.implode(', ', $parts) : null);

            return $item;
        });
    }
}
