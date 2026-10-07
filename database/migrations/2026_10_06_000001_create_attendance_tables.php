<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_logs', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->dateTime('logged_at');
            $table->string('source', 30);
            $table->string('device_id', 100)->nullable();
            $table->string('external_log_id', 150)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'logged_at']);
            $table->index('logged_at');
            $table->index(['source', 'device_id']);
            $table->unique(['source', 'external_log_id']);
        });

        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_log_id')->unique()->constrained('employee_logs')->restrictOnDelete();
            $table->string('employee_id', 30);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->date('attendance_date');
            $table->foreignId('schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('schedule_detail_id')->nullable()->constrained('schedule_details')->restrictOnDelete();
            $table->foreignId('overtime_application_id')->nullable()->constrained('overtime_applications')->restrictOnDelete();
            $table->dateTime('logged_at');
            $table->string('log_type', 20)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('reason')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'attendance_date']);
        });

        Schema::create('employee_attendance', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->date('date');
            $table->foreignId('schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('schedule_detail_id')->nullable()->constrained('schedule_details')->restrictOnDelete();
            $table->foreignId('overtime_application_id')->nullable()->constrained('overtime_applications')->restrictOnDelete();
            $table->dateTime('sched_start')->nullable();
            $table->dateTime('sched_end')->nullable();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            foreach (['tardy', 'undertime', 'early_dismiss'] as $field) {
                $table->boolean($field)->default(false);
                $table->unsignedInteger($field.'_seconds')->default(0);
            }
            $table->boolean('absent')->default(false);
            $table->boolean('missing_time_in')->default(false);
            $table->boolean('missing_time_out')->default(false);
            $table->string('status', 30)->default('present');
            $table->text('remarks')->nullable();
            $table->json('details')->nullable();
            $table->dateTime('computed_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date', 'schedule_detail_id'], 'attendance_block_unique');
            $table->unique(['employee_id', 'date', 'overtime_application_id'], 'attendance_overtime_unique');
            $table->index(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });

        Schema::create('attendance_cutoff_summary', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            foreach (['scheduled', 'rendered', 'credited', 'late', 'undertime', 'break', 'overtime_rendered', 'overtime_approved', 'night_diff', 'regular'] as $field) {
                $table->unsignedInteger($field.'_seconds')->default(0);
            }
            foreach (['present', 'absent', 'incomplete'] as $field) {
                $table->unsignedInteger($field.'_days')->default(0);
            }
            $table->json('details')->nullable();
            $table->dateTime('computed_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date_from', 'date_to'], 'attendance_cutoff_unique');
            $table->index(['date_from', 'date_to']);
        });

        Schema::create('attendance_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->date('date');
            $table->string('type', 30);
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at');
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_attendance_id')->constrained('employee_attendance')->restrictOnDelete();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['attendance_corrections', 'attendance_exceptions', 'attendance_cutoff_summary', 'employee_attendance', 'timesheets', 'employee_logs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
