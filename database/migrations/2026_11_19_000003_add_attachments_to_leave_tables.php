<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            $table->boolean('allow_attachments')->default(false)->after('count_rest_days');
            $table->boolean('require_attachment')->default(false)->after('allow_attachments');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('attachment_path', 255)->nullable()->after('reason');
            $table->string('attachment_name', 255)->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropColumn(['allow_attachments', 'require_attachment']);
        });
    }
};
