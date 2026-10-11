<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_applications', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'disapproved', 'cancelled'])->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('overtime_application_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_application_id')->constrained('overtime_applications')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('break_seconds')->default(0);
            $table->unsignedInteger('requested_seconds');
            $table->timestamps();
            $table->index(['overtime_application_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_application_details');
        Schema::dropIfExists('overtime_applications');
    }
};
