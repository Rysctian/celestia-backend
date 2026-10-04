<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuAccessSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin']);
        $employee = Role::firstOrCreate(['code' => 'employee'], ['name' => 'Employee']);

        $catalog = [
            ['code' => 'dashboard', 'title' => 'Dashboard', 'path' => '/', 'parent' => null, 'sort_order' => 0],
            ['code' => 'daily_time_record', 'title' => 'Daily Time Record', 'path' => null, 'parent' => null, 'sort_order' => 1],
            ['code' => 'schedule_list', 'title' => 'Schedule List', 'path' => '/schedules', 'parent' => 'daily_time_record', 'sort_order' => 0],
            ['code' => 'employee_database', 'title' => 'Employee Database', 'path' => null, 'parent' => null, 'sort_order' => 2],
            ['code' => 'employee_201', 'title' => 'Employee 201 File', 'path' => '/employees', 'parent' => 'employee_database', 'sort_order' => 0],
            ['code' => 'administration', 'title' => 'Administration', 'path' => null, 'parent' => null, 'sort_order' => 3],
            ['code' => 'user_management', 'title' => 'User Management', 'path' => '/users', 'parent' => 'administration', 'sort_order' => 0],
        ];

        foreach ($catalog as $item) {
            $parentId = $item['parent'] ? Menu::where('code', $item['parent'])->value('id') : null;
            $menu = Menu::firstOrCreate(['code' => $item['code']], [
                'title' => $item['title'], 'path' => $item['path'],
                'parent_id' => $parentId, 'sort_order' => $item['sort_order'],
            ]);

            if ($item['path'] === null) {
                continue;
            }

            DB::table('role_menu_access')->insertOrIgnore([
                'role_id' => $admin->id, 'menu_id' => $menu->id,
                'can_view' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            if (in_array($item['code'], ['dashboard', 'employee_201'], true)) {
                DB::table('role_menu_access')->insertOrIgnore([
                    'role_id' => $employee->id, 'menu_id' => $menu->id,
                    'can_view' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
