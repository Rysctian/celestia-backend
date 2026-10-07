<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');

            $table->string('code_day', 3)->virtualAs(
                "CASE day_of_week
                    WHEN 1 THEN 'M'
                    WHEN 2 THEN 'T'
                    WHEN 3 THEN 'W'
                    WHEN 4 THEN 'TH'
                    WHEN 5 THEN 'F'
                    WHEN 6 THEN 'S'
                    WHEN 7 THEN 'SUN'
                END"
            );

            $table->boolean('is_rest_day')->default(false);

            /*
             * Actual schedule
             */
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            /*
             * Attendance thresholds / grace periods
             *
             * Example:
             * start_time = 09:00
             * tardy_start = 09:16
             *
             * 09:00 - 09:15 = not tardy
             * 09:16+       = tardy
             */
            $table->time('tardy_start')->nullable();

            /*
             * Earliest acceptable timeout without undertime.
             *
             * Example:
             * end_time = 18:00
             * undertime_start = 17:45
             *
             * 17:45 - 18:00 = allowed grace
             * before 17:45  = undertime
             */
            $table->time('undertime_start')->nullable();

            /*
             * Optional threshold specifically for early dismissal.
             * Useful if early dismissal has different rules
             * from normal undertime.
             */
            $table->time('early_dismissal_start')->nullable();

            $table->timestamps();

            $table->index(
                'schedule_id',
                'schedule_details_schedule_id_index'
            );

            $table->unique(
                ['schedule_id', 'day_of_week', 'start_time'],
                'schedule_details_slot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_details');
    }
};
