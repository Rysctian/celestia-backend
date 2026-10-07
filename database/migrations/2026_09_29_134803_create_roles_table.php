<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // These IDs also provide a database default for users created outside Eloquent.
        DB::table('roles')->insert([
            ['id' => 1, 'code' => 'admin', 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'employee', 'name' => 'Employee', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
