<?php

use Database\Seeders\MenuAccessSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // These IDs also provide a database default for users created outside Eloquent.
        DB::table('roles')->insert([
            ['id' => 1, 'code' => 'admin', 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'employee', 'name' => 'Employee', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
        });

        DB::table('users')->whereIn('employee_id', DB::table('employees')->select('employee_id')->where('type', 'admin'))
            ->update(['role_id' => 1]);

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('path')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('role_menu_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
            $table->unique(['role_id', 'menu_id']);
        });

        app(MenuAccessSeeder::class)->run();
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::dropIfExists('role_menu_access');
            Schema::dropIfExists('menus');
            Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('role_id'));
            Schema::dropIfExists('roles');
        });
    }
};
