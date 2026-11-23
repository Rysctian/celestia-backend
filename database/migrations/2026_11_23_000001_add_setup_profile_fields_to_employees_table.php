<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 01. Organizational Setup Placement
            $table->foreignId('office_id')
                ->nullable()
                ->after('type')
                ->constrained('offices', 'id', 'employees_office_fk')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->after('office_id')
                ->constrained('departments', 'id', 'employees_department_fk')
                ->nullOnDelete();
            $table->foreignId('division_id')
                ->nullable()
                ->after('department_id')
                ->constrained('divisions', 'id', 'employees_division_fk')
                ->nullOnDelete();
            $table->foreignId('section_id')
                ->nullable()
                ->after('division_id')
                ->constrained('sections', 'id', 'employees_section_fk')
                ->nullOnDelete();
            $table->foreignId('position_id')
                ->nullable()
                ->after('section_id')
                ->constrained('positions', 'id', 'employees_position_fk')
                ->nullOnDelete();
            $table->string('reports_to_employee_id', 30)->nullable()->after('position_id');

            // 02. Employee Information Setup
            $table->string('blood_type', 30)->nullable()->after('civil_status');
            $table->string('country', 60)->nullable()->after('blood_type');
            $table->string('nationality', 60)->nullable()->after('country');
            $table->string('citizenship', 60)->nullable()->after('nationality');
            $table->string('religion', 80)->nullable()->after('citizenship');
            $table->string('educational_attainment', 80)->nullable()->after('religion');
            $table->string('degree_course', 120)->nullable()->after('educational_attainment');
            $table->json('languages')->nullable()->after('degree_course');
            $table->string('disability_type', 80)->nullable()->after('languages');

            $table->string('current_address', 255)->nullable()->after('telephone_no');
            $table->string('emergency_contact_name', 150)->nullable()->after('current_address');
            $table->string('emergency_contact_relationship', 60)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_no', 30)->nullable()->after('emergency_contact_relationship');

            // 03. Employment Setup
            $table->string('employment_status', 60)->nullable()->after('emergency_contact_no');
            $table->string('employee_category', 60)->nullable()->after('employment_status');
            $table->string('appointment_type', 60)->nullable()->after('employee_category');
            $table->string('employment_classification', 60)->nullable()->after('appointment_type');
            $table->string('work_arrangement', 60)->nullable()->after('employment_classification');
            $table->string('work_email', 150)->nullable()->after('work_arrangement');
            $table->date('date_hired')->nullable()->after('work_email');
            $table->date('regularization_date')->nullable()->after('date_hired');
            $table->string('separation_type', 60)->nullable()->after('regularization_date');
            $table->string('separation_reason', 120)->nullable()->after('separation_type');
            $table->date('separation_date')->nullable()->after('separation_reason');

            // Statutory & Compliance IDs
            $table->string('tin_no', 30)->nullable()->after('separation_date');
            $table->string('sss_no', 30)->nullable()->after('tin_no');
            $table->string('philhealth_no', 30)->nullable()->after('sss_no');
            $table->string('pagibig_no', 30)->nullable()->after('philhealth_no');

            $table->index(['office_id', 'department_id', 'position_id'], 'employees_org_placement_idx');
            $table->index('employment_status', 'employees_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_org_placement_idx');
            $table->dropIndex('employees_status_idx');
            $table->dropForeign('employees_office_fk');
            $table->dropForeign('employees_department_fk');
            $table->dropForeign('employees_division_fk');
            $table->dropForeign('employees_section_fk');
            $table->dropForeign('employees_position_fk');

            $table->dropColumn([
                'office_id',
                'department_id',
                'division_id',
                'section_id',
                'position_id',
                'reports_to_employee_id',
                'blood_type',
                'country',
                'nationality',
                'citizenship',
                'religion',
                'educational_attainment',
                'degree_course',
                'languages',
                'disability_type',
                'current_address',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_no',
                'employment_status',
                'employee_category',
                'appointment_type',
                'employment_classification',
                'work_arrangement',
                'work_email',
                'date_hired',
                'regularization_date',
                'separation_type',
                'separation_reason',
                'separation_date',
                'tin_no',
                'sss_no',
                'philhealth_no',
                'pagibig_no',
            ]);
        });
    }
};
