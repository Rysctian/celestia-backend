<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_days')) {
            Schema::rename('schedule_days', 'schedule_details');
        }

        // MySQL needs an index on the foreign key before the old unique index can go.
        if (! Schema::hasIndex('schedule_details', 'schedule_details_schedule_id_index')) {
            Schema::table('schedule_details', function (Blueprint $table) {
                $table->index('schedule_id', 'schedule_details_schedule_id_index');
            });
        }
        if (Schema::hasIndex('schedule_details', 'schedule_days_schedule_id_day_of_week_unique')) {
            Schema::table('schedule_details', function (Blueprint $table) {
                $table->dropUnique('schedule_days_schedule_id_day_of_week_unique');
            });
        }
        if (! Schema::hasIndex('schedule_details', 'schedule_details_slot_unique')) {
            Schema::table('schedule_details', function (Blueprint $table) {
                $table->unique(['schedule_id', 'day_of_week', 'start_time', 'ends_next_day'], 'schedule_details_slot_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::table('schedule_details')
            ->select('schedule_id', 'day_of_week')
            ->groupBy('schedule_id', 'day_of_week')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Cannot restore schedule_days while a weekday has multiple slots.');
        }

        Schema::table('schedule_details', function (Blueprint $table) {
            $table->dropUnique('schedule_details_slot_unique');
            $table->unique(['schedule_id', 'day_of_week'], 'schedule_days_schedule_id_day_of_week_unique');
        });
        Schema::table('schedule_details', function (Blueprint $table) {
            $table->dropIndex('schedule_details_schedule_id_index');
        });
        Schema::rename('schedule_details', 'schedule_days');
    }
};
