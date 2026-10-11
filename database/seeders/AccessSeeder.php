<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin']);
        $employee = Role::firstOrCreate(['code' => 'employee'], ['name' => 'Employee']);

        foreach (Menu::whereIn('code', ['dashboard', 'schedule_list', 'attendance', 'employee_201', 'user_management', 'cut_off', 'approver_setup', 'overtime_application'])->get() as $menu) {
            DB::table('role_menu_access')->insertOrIgnore([
                'role_id' => $admin->id, 'menu_id' => $menu->id,
                'can_view' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            if (in_array($menu->code, ['dashboard', 'employee_201', 'overtime_application'], true)) {
                DB::table('role_menu_access')->insertOrIgnore([
                    'role_id' => $employee->id, 'menu_id' => $menu->id,
                    'can_view' => true, 'can_create' => $menu->code === 'overtime_application', 'can_update' => $menu->code === 'overtime_application', 'can_delete' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
