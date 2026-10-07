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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30)->unique();
            $table->string('type')->nullable(); // admin/ employee
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('name_extension', 20)->nullable();

            $table->date('birth_date')->nullable();
            $table->string('birth_place', 255)->nullable();

            $table->string('gender', 20)->nullable();
            $table->string('civil_status', 30)->nullable();

            $table->string('personal_email')->nullable();
            $table->string('mobile_no', 30)->nullable();
            $table->string('telephone_no', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
