<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MenuAccessService
{
    public function treeFor(User $user): array
    {
        $grants = $user->role->menus()
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $menus = Menu::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return $menus->whereNull('parent_id')->map(function (Menu $parent) use ($menus, $grants) {
            $children = $menus->where('parent_id', $parent->id)
                ->filter(fn (Menu $menu) => (bool) ($grants->get($menu->id)?->pivot->can_view))
                ->map(fn (Menu $menu) => $this->menuWithActions($menu, $grants->get($menu->id)))
                ->values()->all();

            if ($parent->path === null && $children === []) {
                return null;
            }

            if ($parent->path !== null && ! $grants->get($parent->id)?->pivot->can_view) {
                return null;
            }

            return [
                ...$this->menuWithActions($parent, $grants->get($parent->id)),
                'children' => $children,
            ];
        })->filter()->values()->all();
    }

    public function catalog(): array
    {
        $menus = Menu::orderBy('sort_order')->orderBy('id')->get();

        return $menus->whereNull('parent_id')->map(fn (Menu $menu) => [
            ...$menu->only(['id', 'code', 'title', 'path', 'sort_order', 'is_active']),
            'children' => $menus->where('parent_id', $menu->id)
                ->map(fn (Menu $child) => $child->only(['id', 'code', 'title', 'path', 'sort_order', 'is_active']))
                ->values()->all(),
        ])->values()->all();
    }

    public function grants(Role $role): array
    {
        return $role->menus()->orderBy('menus.id')->get()->map(fn (Menu $menu) => [
            'menu_id' => $menu->id,
            'can_view' => (bool) $menu->pivot->can_view,
            'can_create' => (bool) $menu->pivot->can_create,
            'can_update' => (bool) $menu->pivot->can_update,
            'can_delete' => (bool) $menu->pivot->can_delete,
        ])->all();
    }

    public function replaceGrants(Role $role, array $access): array
    {
        $ids = array_column($access, 'menu_id');
        $validCount = Menu::whereIn('id', $ids)->whereNotNull('path')->where('is_active', true)->count();
        if ($validCount !== count($ids)) {
            throw ValidationException::withMessages(['access' => 'Grant access only to active menu items.']);
        }

        $sync = [];
        foreach ($access as $grant) {
            $sync[$grant['menu_id']] = [
                'can_view' => $grant['can_view'],
                'can_create' => $grant['can_create'],
                'can_update' => $grant['can_update'],
                'can_delete' => $grant['can_delete'],
            ];
        }

        DB::transaction(fn () => $role->menus()->sync($sync));

        return $this->grants($role);
    }

    private function menuWithActions(Menu $menu, ?Menu $grant): array
    {
        return [
            'id' => $menu->id,
            'code' => $menu->code,
            'title' => $menu->title,
            'path' => $menu->path,
            'sort_order' => $menu->sort_order,
            'actions' => [
                'view' => (bool) ($grant?->pivot->can_view),
                'create' => (bool) ($grant?->pivot->can_create),
                'update' => (bool) ($grant?->pivot->can_update),
                'delete' => (bool) ($grant?->pivot->can_delete),
            ],
        ];
    }
}
