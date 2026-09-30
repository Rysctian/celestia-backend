<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('overtime_applications', function (Blueprint $table) {
            $table->id();
             $table->string('employee_id', 30);

            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->date('overtime_date');
            $table->time('time_from');
            $table->time('time_to');
            $table->text('reason');
            $table->boolean('allow_approver');
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'overtime_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_applications');
    }
};