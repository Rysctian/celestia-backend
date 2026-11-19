<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_applications', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'disapproved', 'cancelled'])->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('correction_application_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correction_application_id')
                ->constrained('correction_applications', 'id', 'corr_app_details_app_id_fk')
                ->cascadeOnDelete();
            $table->date('date');
            $table->time('am_in')->nullable();
            $table->time('am_out')->nullable();
            $table->time('pm_in')->nullable();
            $table->time('pm_out')->nullable();
            $table->timestamps();
            $table->unique(['correction_application_id', 'date'], 'corr_app_details_app_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_application_details');
        Schema::dropIfExists('correction_applications');
    }
};
