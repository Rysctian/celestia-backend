<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_cutoffs', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_type', 20);
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('no_dtr')->default(false);
            $table->date('dtr_cutoff_from')->nullable();
            $table->date('dtr_cutoff_to')->nullable();
            $table->date('dtr_confirmation_start');
            $table->date('dtr_confirmation_end');
            $table->time('dtr_confirmation_time_from');
            $table->time('dtr_confirmation_time_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
            $table->index(['schedule_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_cutoffs');
    }
};
