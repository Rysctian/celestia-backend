<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendance', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->date('date');
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->restrictOnDelete();

            $table->time('sched_start')->nullable();
            $table->time('sched_end')->nullable();
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();

            $table->unsignedInteger('scheduled_seconds')->default(0);
            $table->unsignedInteger('rendered_seconds')->default(0);
            $table->boolean('absent')->default(false);

            $table->unsignedInteger('tardy_seconds')->default(0);
            $table->boolean('undertime')->default(false);
            $table->unsignedInteger('undertime_seconds')->default(0);
            $table->boolean('early_dismiss')->default(false);
            $table->boolean('holiday')->default(false);
            $table->boolean('suspended')->default(false);

            $table->text('remarks')->nullable();
            $table->string('status', 30)->default('pending');
            $table->json('details')->nullable();
            $table->dateTime('confirmed_at')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'date'], 'employee_attendance_unique');

            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance');
    }
};
