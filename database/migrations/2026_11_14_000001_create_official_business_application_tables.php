<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_business_applications', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30);
            $table->enum('ob_type', ['ob', 'wfh'])->default('ob');
            $table->string('destination', 255)->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'disapproved', 'cancelled'])->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            $table->index(['employee_id', 'status']);
            $table->index('ob_type');
        });

        Schema::create('official_business_application_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_business_application_id')
                ->constrained('official_business_applications', 'id', 'ob_app_details_app_id_fk')
                ->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->index(['official_business_application_id', 'date'], 'ob_app_details_app_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_application_details');
        Schema::dropIfExists('official_business_applications');
    }
};
