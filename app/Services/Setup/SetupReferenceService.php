<?php

namespace App\Services\Setup;

use App\Models\Setup\SetupReferenceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetupReferenceService
{
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
        return SetupReferenceItem::query()
            ->where('group', $group)
            ->with(['parent:id,category,code,name'])
            ->withCount(['children'])
            ->filterSearch($request)
            ->get();
    }

    public function findById(string $group, int $id): ?SetupReferenceItem
    {
        return SetupReferenceItem::query()
            ->where('group', $group)
            ->with(['parent:id,category,code,name'])
            ->withCount(['children'])
            ->find($id);
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
            $item = SetupReferenceItem::where('group', $group)->lockForUpdate()->findOrFail($id);

            if ($item->children()->exists()) {
                throw ValidationException::withMessages([
                    'item' => 'Cannot delete this setup item because child items depend on it.',
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
}
