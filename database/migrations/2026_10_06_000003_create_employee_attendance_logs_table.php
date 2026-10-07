<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_attendance_id')->constrained('employee_attendance')->cascadeOnDelete();
            $table->foreignId('employee_log_id')->constrained('employee_logs')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('log_type', 20);
            $table->timestamps();
            $table->unique(['employee_attendance_id', 'employee_log_id'], 'attendance_log_unique');
            $table->unique(
                ['employee_attendance_id', 'sequence'],
                'attendance_log_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_logs');
    }
};
