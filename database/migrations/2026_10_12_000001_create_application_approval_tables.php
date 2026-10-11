<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approver_setup', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->string('application_type', 30);
            $table->string('approver_id', 30);
            $table->unsignedInteger('sequence');
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            $table->foreign('approver_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->unique(['employee_id', 'application_type', 'sequence'], 'approver_setup_sequence_unique');
        });

        Schema::create('application_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->string('application_type', 30);
            $table->unsignedBigInteger('application_id');
            $table->string('approver_id', 30);
            $table->unsignedInteger('sequence');
            $table->enum('status', ['pending', 'approved', 'disapproved', 'cancelled'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            $table->foreign('approver_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->unique(['application_type', 'application_id', 'sequence'], 'application_approvals_sequence_unique');
            $table->index(['approver_id', 'status']);
            $table->index(['employee_id', 'application_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_approvals');
        Schema::dropIfExists('approver_setup');
    }
};
