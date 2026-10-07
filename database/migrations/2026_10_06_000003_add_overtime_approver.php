<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_applications', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('overtime_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
        });
    }
};
