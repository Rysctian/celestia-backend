<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreignId('schedule_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'effective_from']);
            $table->index(['employee_id', 'effective_from', 'effective_to'], 'employee_schedule_dates_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
