<?php

use Database\Seeders\MenuAccessSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('tardy_grace_seconds')->default(0);
            $table->string('tardy_count_from', 30)->default('schedule_start');
            $table->unsignedInteger('absent_after_late_seconds')->nullable();
            $table->unsignedInteger('undertime_grace_seconds')->default(0);
            $table->unsignedInteger('early_in_window_seconds')->default(14400);
            $table->unsignedInteger('late_out_window_seconds')->default(14400);
            $table->unsignedInteger('duplicate_tap_window_seconds')->default(120);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });

        app(MenuAccessSeeder::class)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
