<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->string('code_day', 3)->virtualAs(
                "CASE day_of_week WHEN 1 THEN 'M' WHEN 2 THEN 'T' WHEN 3 THEN 'W' WHEN 4 THEN 'TH' WHEN 5 THEN 'F' WHEN 6 THEN 'S' WHEN 7 THEN 'SUN' END"
            );
            $table->boolean('is_rest_day')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('ends_next_day')->default(false);
            $table->unsignedSmallInteger('unpaid_break_minutes')->default(0);
            $table->timestamps();
            $table->unique(['schedule_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_days');
    }
};