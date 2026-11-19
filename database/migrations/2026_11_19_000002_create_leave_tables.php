<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('min_notice_days')->default(0);
            $table->boolean('allow_backdated_filing')->default(false);
            $table->unsignedSmallInteger('max_backdated_days')->nullable();
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->boolean('allow_half_day')->default(true);
            $table->string('accrual_mode', 30)->default('upfront_annual');
            $table->boolean('allow_negative_balance')->default(false);
            $table->boolean('carry_over_enabled')->default(false);
            $table->decimal('carry_over_max_days', 6, 2)->default(0);
            $table->boolean('count_rest_days')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_default', 'is_active']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->foreignId('leave_policy_id')
                ->nullable()
                ->constrained('leave_policies', 'id', 'leave_types_policy_id_fk')
                ->nullOnDelete();
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_credits')->default(true);
            $table->decimal('default_credits', 6, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('employee_leave_credits', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->foreignId('leave_type_id')
                ->constrained('leave_types', 'id', 'emp_leave_credits_type_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated_credits', 6, 2)->default(0);
            $table->decimal('used_credits', 6, 2)->default(0);
            $table->decimal('pending_credits', 6, 2)->default(0);
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            $table->unique(['employee_id', 'leave_type_id', 'year'], 'emp_leave_credits_unique');
            $table->index(['employee_id', 'year']);
        });

        Schema::create('employee_leave_credit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_leave_credit_id')
                ->constrained('employee_leave_credits', 'id', 'emp_leave_credit_logs_credit_fk')
                ->cascadeOnDelete();
            $table->string('change_type', 30);
            $table->decimal('amount', 6, 2);
            $table->decimal('balance_after', 6, 2);
            $table->unsignedBigInteger('leave_application_id')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->string('acted_by', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('leave_application_id');
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->foreignId('leave_type_id')
                ->constrained('leave_types', 'id', 'leave_apps_type_fk')
                ->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('total_days', 6, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'disapproved', 'cancelled'])->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('leave_application_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')
                ->constrained('leave_applications', 'id', 'leave_app_details_app_fk')
                ->cascadeOnDelete();
            $table->date('date');
            $table->enum('duration_type', ['whole_day', 'half_day_am', 'half_day_pm'])->default('whole_day');
            $table->decimal('day_value', 4, 2)->default(1.00);
            $table->timestamps();
            $table->unique(['leave_application_id', 'date'], 'leave_app_details_app_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_application_details');
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('employee_leave_credit_logs');
        Schema::dropIfExists('employee_leave_credits');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('leave_policies');
    }
};
