<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cut_offs', function (Blueprint $table) {
            $table->id();
            $table->enum('schedule_type', ['monthly', 'semi_monthly']);
            $table->string('name', 100);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['year', 'month', 'schedule_type']);
            $table->index('is_active');
        });

        Schema::create('cut_off_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cut_off_id')->constrained('cut_offs')->cascadeOnDelete();
            $table->unsignedTinyInteger('cut_off_no');
            $table->date('dtr_date_from')->nullable();
            $table->date('dtr_date_to')->nullable();
            $table->boolean('no_dtr')->default(false);
            $table->date('confirmation_start_date')->nullable();
            $table->date('confirmation_end_date')->nullable();
            $table->time('confirmation_time_from')->nullable();
            $table->time('confirmation_time_to')->nullable();
            $table->date('payroll_date_from');
            $table->date('payroll_date_to');
            $table->date('release_date');
            $table->timestamps();
            $table->unique(['cut_off_id', 'cut_off_no']);
            $table->index(['dtr_date_from', 'dtr_date_to']);
            $table->index(['payroll_date_from', 'payroll_date_to']);
            $table->index('release_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cut_off_periods');
        Schema::dropIfExists('cut_offs');
    }
};
