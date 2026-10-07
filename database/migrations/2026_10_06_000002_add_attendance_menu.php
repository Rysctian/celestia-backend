<?php

use App\Models\Menu;
use Database\Seeders\MenuAccessSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MenuAccessSeeder::class)->run();
    }

    public function down(): void
    {
        Menu::where('code', 'attendance')->delete();
    }
};
