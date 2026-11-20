<?php

namespace Database\Seeders\Setup;

use App\Models\Setup\Department;
use App\Models\Setup\Division;
use App\Models\Setup\Office;
use App\Models\Setup\Position;
use App\Models\Setup\ReportsItem;
use App\Models\Setup\Section;
use Illuminate\Database\Seeder;

class OrganizationalSetupSeeder extends Seeder
{
    public function run(): void
    {
        $execOffice = Office::firstOrCreate(
            ['code' => 'OEXEC'],
            [
                'name' => 'Office of the President',
                'short_name' => 'EXEC',
                'type' => 'office',
                'description' => 'Executive leadership and institutional governance.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $adminOffice = Office::firstOrCreate(
            ['code' => 'OADMIN'],
            [
                'name' => 'Office of Administration and Finance',
                'short_name' => 'ADMIN',
                'type' => 'office',
                'parent_id' => $execOffice->id,
                'description' => 'Administrative operations, human resources, finance, and IT.',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $ccsCollege = Office::firstOrCreate(
            ['code' => 'CCS'],
            [
                'name' => 'College of Computing Studies',
                'short_name' => 'CCS',
                'type' => 'college',
                'parent_id' => $execOffice->id,
                'description' => 'Academic college overseeing computing and information technology programs.',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $hrDept = Department::firstOrCreate(
            ['code' => 'HRD'],
            [
                'name' => 'Human Resources Department',
                'short_name' => 'HR',
                'office_id' => $adminOffice->id,
                'description' => 'Talent management, employee relations, compensation, and compliance.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $finDept = Department::firstOrCreate(
            ['code' => 'FIN'],
            [
                'name' => 'Finance and Accounting Department',
                'short_name' => 'Finance',
                'office_id' => $adminOffice->id,
                'description' => 'Financial planning, treasury, disbursement, and accounting.',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $itDept = Department::firstOrCreate(
            ['code' => 'ITD'],
            [
                'name' => 'Information Technology Department',
                'short_name' => 'IT',
                'office_id' => $adminOffice->id,
                'description' => 'Enterprise systems, infrastructure, and technical support.',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        Department::firstOrCreate(
            ['code' => 'CS'],
            [
                'name' => 'Computer Science Department',
                'short_name' => 'CS',
                'office_id' => $ccsCollege->id,
                'description' => 'Academic department for computer science faculty and curriculum.',
                'sort_order' => 4,
                'is_active' => true,
            ]
        );

        $hrOpsDiv = Division::firstOrCreate(
            ['code' => 'HR-OPS'],
            [
                'name' => 'HR Operations Division',
                'short_name' => 'HR Ops',
                'department_id' => $hrDept->id,
                'description' => 'Handles recruitment, 201 records, and employee lifecycle operations.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $compBenDiv = Division::firstOrCreate(
            ['code' => 'HR-COMP'],
            [
                'name' => 'Compensation and Benefits Division',
                'short_name' => 'Comp & Ben',
                'department_id' => $hrDept->id,
                'description' => 'Handles payroll inputs, timekeeping, leave, and statutory benefits.',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $sysEngDiv = Division::firstOrCreate(
            ['code' => 'IT-ENG'],
            [
                'name' => 'Systems Engineering Division',
                'short_name' => 'SysEng',
                'department_id' => $itDept->id,
                'description' => 'Software engineering and internal enterprise platforms.',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $recruitSec = Section::firstOrCreate(
            ['code' => 'SEC-REC'],
            [
                'name' => 'Recruitment and Onboarding Unit',
                'short_name' => 'Recruitment',
                'division_id' => $hrOpsDiv->id,
                'department_id' => $hrDept->id,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $payrollSec = Section::firstOrCreate(
            ['code' => 'SEC-PAY'],
            [
                'name' => 'Timekeeping and Payroll Unit',
                'short_name' => 'Payroll',
                'division_id' => $compBenDiv->id,
                'department_id' => $hrDept->id,
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $hrisSec = Section::firstOrCreate(
            ['code' => 'SEC-HRIS'],
            [
                'name' => 'Core HRIS Platform Unit',
                'short_name' => 'HRIS Unit',
                'division_id' => $sysEngDiv->id,
                'department_id' => $itDept->id,
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $hrDir = Position::firstOrCreate(
            ['code' => 'POS-HRDIR'],
            [
                'title' => 'Human Resources Director',
                'department_id' => $hrDept->id,
                'salary_grade' => 'SG-24',
                'job_level' => 'Executive',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $hrMgr = Position::firstOrCreate(
            ['code' => 'POS-HRMGR'],
            [
                'title' => 'HR Operations Manager',
                'department_id' => $hrDept->id,
                'division_id' => $hrOpsDiv->id,
                'reports_to_position_id' => $hrDir->id,
                'salary_grade' => 'SG-20',
                'job_level' => 'Managerial',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $hrSpec = Position::firstOrCreate(
            ['code' => 'POS-HRSPEC'],
            [
                'title' => 'HR Specialist',
                'department_id' => $hrDept->id,
                'division_id' => $hrOpsDiv->id,
                'section_id' => $recruitSec->id,
                'reports_to_position_id' => $hrMgr->id,
                'salary_grade' => 'SG-15',
                'job_level' => 'Professional',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $payrollOfficer = Position::firstOrCreate(
            ['code' => 'POS-PAYOFF'],
            [
                'title' => 'Payroll and Benefits Officer',
                'department_id' => $hrDept->id,
                'division_id' => $compBenDiv->id,
                'section_id' => $payrollSec->id,
                'reports_to_position_id' => $hrDir->id,
                'salary_grade' => 'SG-16',
                'job_level' => 'Professional',
                'sort_order' => 4,
                'is_active' => true,
            ]
        );

        $itMgr = Position::firstOrCreate(
            ['code' => 'POS-ITMGR'],
            [
                'title' => 'IT Systems Manager',
                'department_id' => $itDept->id,
                'division_id' => $sysEngDiv->id,
                'salary_grade' => 'SG-22',
                'job_level' => 'Managerial',
                'sort_order' => 5,
                'is_active' => true,
            ]
        );

        $swe = Position::firstOrCreate(
            ['code' => 'POS-SWE'],
            [
                'title' => 'Software Engineer',
                'department_id' => $itDept->id,
                'division_id' => $sysEngDiv->id,
                'section_id' => $hrisSec->id,
                'reports_to_position_id' => $itMgr->id,
                'salary_grade' => 'SG-18',
                'job_level' => 'Professional',
                'sort_order' => 6,
                'is_active' => true,
            ]
        );

        Position::firstOrCreate(
            ['code' => 'POS-FINMGR'],
            [
                'title' => 'Finance Manager',
                'department_id' => $finDept->id,
                'salary_grade' => 'SG-22',
                'job_level' => 'Managerial',
                'sort_order' => 7,
                'is_active' => true,
            ]
        );

        ReportsItem::firstOrCreate(
            ['code' => 'RPT-HR-001'],
            [
                'name' => 'HR Specialist Reporting Line',
                'office_id' => $adminOffice->id,
                'department_id' => $hrDept->id,
                'division_id' => $hrOpsDiv->id,
                'section_id' => $recruitSec->id,
                'position_id' => $hrSpec->id,
                'reports_to_position_id' => $hrMgr->id,
                'description' => 'Standard reporting line for HR recruitment specialists.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        ReportsItem::firstOrCreate(
            ['code' => 'RPT-HR-002'],
            [
                'name' => 'Payroll Officer Reporting Line',
                'office_id' => $adminOffice->id,
                'department_id' => $hrDept->id,
                'division_id' => $compBenDiv->id,
                'section_id' => $payrollSec->id,
                'position_id' => $payrollOfficer->id,
                'reports_to_position_id' => $hrDir->id,
                'description' => 'Standard reporting line for payroll and timekeeping officers.',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        ReportsItem::firstOrCreate(
            ['code' => 'RPT-IT-001'],
            [
                'name' => 'HRIS Software Engineer Reporting Line',
                'office_id' => $adminOffice->id,
                'department_id' => $itDept->id,
                'division_id' => $sysEngDiv->id,
                'section_id' => $hrisSec->id,
                'position_id' => $swe->id,
                'reports_to_position_id' => $itMgr->id,
                'description' => 'Standard reporting line for core HRIS platform engineers.',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );
    }
}
