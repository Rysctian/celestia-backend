<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correction_application_details', function (Blueprint $table) {
            $table->time('old_am_in')->nullable()->after('date');
            $table->time('old_am_out')->nullable()->after('old_am_in');
            $table->time('old_pm_in')->nullable()->after('old_am_out');
            $table->time('old_pm_out')->nullable()->after('old_pm_in');
        });
    }

    public function down(): void
    {
        Schema::table('correction_application_details', function (Blueprint $table) {
            $table->dropColumn(['old_am_in', 'old_am_out', 'old_pm_in', 'old_pm_out']);
        });
    }
};
