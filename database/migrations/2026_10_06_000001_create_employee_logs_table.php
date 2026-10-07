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
            $table->string('device_id', 100)->nullable();
            $table->string('source', 30)->default('biometric');
            $table->timestamps();
            $table->index(['employee_id', 'logged_at']);
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_logs');
    }
};
