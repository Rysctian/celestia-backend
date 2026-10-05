<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['code' => 'dashboard',         'title' => 'Dashboard',         'path' => '/',          'parent' => null,                 'sort_order' => 0],
            ['code' => 'daily_time_record', 'title' => 'Daily Time Record', 'path' => null,         'parent' => null,                 'sort_order' => 1],
            ['code' => 'schedule_list',     'title' => 'Schedule List',     'path' => '/schedules', 'parent' => 'daily_time_record',  'sort_order' => 0],
            ['code' => 'employee_database', 'title' => 'Employee Database', 'path' => null,         'parent' => null,                 'sort_order' => 2],
            ['code' => 'employee_201',      'title' => 'Employee 201 File', 'path' => '/employees', 'parent' => 'employee_database',  'sort_order' => 0],
            ['code' => 'administration',    'title' => 'Administration',    'path' => null,         'parent' => null,                 'sort_order' => 3],
            ['code' => 'user_management',   'title' => 'User Management',   'path' => '/user-management',     'parent' => 'administration',     'sort_order' => 0],
        ];

        foreach ($catalog as $item) {
            $parentId = $item['parent'] ? Menu::where('code', $item['parent'])->value('id') : null;

            Menu::firstOrCreate(['code' => $item['code']], [
                'title' => $item['title'], 'path' => $item['path'],
                'parent_id' => $parentId, 'sort_order' => $item['sort_order'],
            ]);
        }
    }
}
