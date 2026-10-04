<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('schedule_details')->where('ends_next_day', true)
            ->orWhereColumn('end_time', '<=', 'start_time')->exists()) {
            throw new RuntimeException('Review schedule details that end on the next day before removing ends_next_day.');
        }

        Schema::table('schedule_details', function (Blueprint $table) {
            $table->dropUnique('schedule_details_slot_unique');
            $table->dropColumn('ends_next_day');
        });

        Schema::table('schedule_details', function (Blueprint $table) {
            $table->unique(['schedule_id', 'day_of_week', 'start_time'], 'schedule_details_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_details', function (Blueprint $table) {
            $table->dropUnique('schedule_details_slot_unique');
            $table->boolean('ends_next_day')->default(false);
        });

        Schema::table('schedule_details', function (Blueprint $table) {
            $table->unique(['schedule_id', 'day_of_week', 'start_time', 'ends_next_day'], 'schedule_details_slot_unique');
        });
    }
};
