<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_cutoff_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_cutoff_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('cutoff_no');
            $table->date('release_date');
            $table->timestamps();

            $table->unique(['payroll_cutoff_id', 'cutoff_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_cutoff_releases');
    }
};
