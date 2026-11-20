<?php

use Database\Seeders\MenuAccessSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('short_name', 50)->nullable();
            $table->string('type', 30)->default('office');
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('offices', 'id', 'offices_parent_fk')
                ->nullOnDelete();
            $table->string('head_employee_id', 30)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('head_employee_id', 'offices_head_emp_fk')
                ->references('employee_id')
                ->on('employees')
                ->nullOnDelete();
            $table->index(['type', 'is_active']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('short_name', 50)->nullable();
            $table->foreignId('office_id')
                ->nullable()
                ->constrained('offices', 'id', 'departments_office_fk')
                ->nullOnDelete();
            $table->string('head_employee_id', 30)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('head_employee_id', 'departments_head_emp_fk')
                ->references('employee_id')
                ->on('employees')
                ->nullOnDelete();
            $table->index(['office_id', 'is_active']);
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('short_name', 50)->nullable();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'divisions_department_fk')
                ->nullOnDelete();
            $table->string('head_employee_id', 30)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('head_employee_id', 'divisions_head_emp_fk')
                ->references('employee_id')
                ->on('employees')
                ->nullOnDelete();
            $table->index(['department_id', 'is_active']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('short_name', 50)->nullable();
            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions', 'id', 'sections_division_fk')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'sections_department_fk')
                ->nullOnDelete();
            $table->string('head_employee_id', 30)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('head_employee_id', 'sections_head_emp_fk')
                ->references('employee_id')
                ->on('employees')
                ->nullOnDelete();
            $table->index(['division_id', 'department_id', 'is_active'], 'sections_div_dept_active_idx');
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('title', 150);
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'positions_department_fk')
                ->nullOnDelete();
            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions', 'id', 'positions_division_fk')
                ->nullOnDelete();
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections', 'id', 'positions_section_fk')
                ->nullOnDelete();
            $table->foreignId('reports_to_position_id')
                ->nullable()
                ->constrained('positions', 'id', 'positions_reports_to_fk')
                ->nullOnDelete();
            $table->string('salary_grade', 30)->nullable();
            $table->string('job_level', 50)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
        });

        Schema::create('reports_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->foreignId('office_id')
                ->nullable()
                ->constrained('offices', 'id', 'reports_items_office_fk')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'reports_items_department_fk')
                ->nullOnDelete();
            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions', 'id', 'reports_items_division_fk')
                ->nullOnDelete();
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections', 'id', 'reports_items_section_fk')
                ->nullOnDelete();
            $table->foreignId('position_id')
                ->nullable()
                ->constrained('positions', 'id', 'reports_items_position_fk')
                ->nullOnDelete();
            $table->foreignId('reports_to_position_id')
                ->nullable()
                ->constrained('positions', 'id', 'reports_items_reports_to_pos_fk')
                ->nullOnDelete();
            $table->string('reports_to_employee_id', 30)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('reports_to_employee_id', 'reports_items_reports_to_emp_fk')
                ->references('employee_id')
                ->on('employees')
                ->nullOnDelete();
            $table->index(['office_id', 'department_id', 'is_active'], 'reports_items_org_active_idx');
        });

        Schema::create('setup_reference_items', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40);
            $table->string('category', 60);
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('setup_reference_items', 'id', 'setup_ref_items_parent_fk')
                ->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category', 'code'], 'setup_ref_items_category_code_unique');
            $table->index(['group', 'category', 'is_active'], 'setup_ref_items_group_cat_active_idx');
        });

        Schema::create('clearance_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->string('applies_to', 50)->default('separation');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['applies_to', 'is_active']);
        });

        Schema::create('clearance_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_type_id')
                ->constrained('clearance_types', 'id', 'clearance_reqs_type_fk')
                ->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->foreignId('office_id')
                ->nullable()
                ->constrained('offices', 'id', 'clearance_reqs_office_fk')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'clearance_reqs_department_fk')
                ->nullOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clearance_type_id', 'code'], 'clearance_reqs_type_code_unique');
            $table->index(['clearance_type_id', 'is_active'], 'clearance_reqs_type_active_idx');
        });

        Schema::create('clearance_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_type_id')
                ->constrained('clearance_types', 'id', 'clearance_sigs_type_fk')
                ->cascadeOnDelete();
            $table->foreignId('clearance_requirement_id')
                ->nullable()
                ->constrained('clearance_requirements', 'id', 'clearance_sigs_req_fk')
                ->cascadeOnDelete();
            $table->foreignId('office_id')
                ->nullable()
                ->constrained('offices', 'id', 'clearance_sigs_office_fk')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'clearance_sigs_department_fk')
                ->nullOnDelete();
            $table->foreignId('position_id')
                ->nullable()
                ->constrained('positions', 'id', 'clearance_sigs_position_fk')
                ->nullOnDelete();
            $table->string('employee_id', 30)->nullable();
            $table->string('signatory_label', 150);
            $table->unsignedSmallInteger('sign_order')->default(1);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('employee_id', 'clearance_sigs_employee_fk')
                ->references('employee_id')
                ->on('employees')
                ->nullOnDelete();
            $table->index(['clearance_type_id', 'sign_order', 'is_active'], 'clearance_sigs_type_order_idx');
        });

        // Idempotently ensure the new Setup sidebar group and admin grants exist on existing databases.
        app(MenuAccessSeeder::class)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_signatories');
        Schema::dropIfExists('clearance_requirements');
        Schema::dropIfExists('clearance_types');
        Schema::dropIfExists('setup_reference_items');
        Schema::dropIfExists('reports_items');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('offices');
    }
};
