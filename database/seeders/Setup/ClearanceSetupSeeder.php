<?php

namespace Database\Seeders\Setup;

use App\Models\Setup\ClearanceRequirement;
use App\Models\Setup\ClearanceSignatory;
use App\Models\Setup\ClearanceType;
use App\Models\Setup\Department;
use App\Models\Setup\Office;
use App\Models\Setup\Position;
use Illuminate\Database\Seeder;

class ClearanceSetupSeeder extends Seeder
{
    public function run(): void
    {
        $adminOfficeId = Office::where('code', 'OADMIN')->value('id');
        $hrDeptId = Department::where('code', 'HRD')->value('id');
        $finDeptId = Department::where('code', 'FIN')->value('id');
        $itDeptId = Department::where('code', 'ITD')->value('id');

        $hrMgrPosId = Position::where('code', 'POS-HRMGR')->value('id');
        $itMgrPosId = Position::where('code', 'POS-ITMGR')->value('id');
        $finMgrPosId = Position::where('code', 'POS-FINMGR')->value('id');

        $exitClearance = ClearanceType::firstOrCreate(
            ['code' => 'CLR-EXIT'],
            [
                'name' => 'Employee Separation / Exit Clearance',
                'description' => 'Mandatory clearance prior to final pay release for resigning, retiring, or separated employees.',
                'applies_to' => 'separation',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        ClearanceType::firstOrCreate(
            ['code' => 'CLR-TRANS'],
            [
                'name' => 'Internal Transfer Clearance',
                'description' => 'Accountability turnover clearance for inter-department or inter-office transfers.',
                'applies_to' => 'transfer',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        ClearanceType::firstOrCreate(
            ['code' => 'CLR-LEAVE'],
            [
                'name' => 'Extended Leave Clearance',
                'description' => 'Handover clearance for sabbatical, study leave, or extended leaves.',
                'applies_to' => 'leave',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $itReq = ClearanceRequirement::firstOrCreate(
            ['clearance_type_id' => $exitClearance->id, 'code' => 'REQ-IT'],
            [
                'name' => 'IT Equipment & System Access Turnover',
                'description' => 'Return company laptop, peripherals, and revoke email/VPN/system credentials.',
                'office_id' => $adminOfficeId,
                'department_id' => $itDeptId,
                'is_mandatory' => true,
                'requires_attachment' => true,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $finReq = ClearanceRequirement::firstOrCreate(
            ['clearance_type_id' => $exitClearance->id, 'code' => 'REQ-FIN'],
            [
                'name' => 'Finance & Cash Advance Liquidation',
                'description' => 'Settle pending cash advances, company loans, and corporate card balances.',
                'office_id' => $adminOfficeId,
                'department_id' => $finDeptId,
                'is_mandatory' => true,
                'requires_attachment' => false,
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $hrReq = ClearanceRequirement::firstOrCreate(
            ['clearance_type_id' => $exitClearance->id, 'code' => 'REQ-HR'],
            [
                'name' => 'HR Exit Interview & Company ID Surrender',
                'description' => 'Complete exit interview and return company ID badge and HMO card.',
                'office_id' => $adminOfficeId,
                'department_id' => $hrDeptId,
                'is_mandatory' => true,
                'requires_attachment' => false,
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        ClearanceSignatory::firstOrCreate(
            [
                'clearance_type_id' => $exitClearance->id,
                'clearance_requirement_id' => $itReq->id,
                'signatory_label' => 'IT Systems Manager',
            ],
            [
                'office_id' => $adminOfficeId,
                'department_id' => $itDeptId,
                'position_id' => $itMgrPosId,
                'sign_order' => 1,
                'is_required' => true,
                'is_active' => true,
            ]
        );

        ClearanceSignatory::firstOrCreate(
            [
                'clearance_type_id' => $exitClearance->id,
                'clearance_requirement_id' => $finReq->id,
                'signatory_label' => 'Finance Manager',
            ],
            [
                'office_id' => $adminOfficeId,
                'department_id' => $finDeptId,
                'position_id' => $finMgrPosId,
                'sign_order' => 2,
                'is_required' => true,
                'is_active' => true,
            ]
        );

        ClearanceSignatory::firstOrCreate(
            [
                'clearance_type_id' => $exitClearance->id,
                'clearance_requirement_id' => $hrReq->id,
                'signatory_label' => 'HR Operations Manager',
            ],
            [
                'office_id' => $adminOfficeId,
                'department_id' => $hrDeptId,
                'position_id' => $hrMgrPosId,
                'sign_order' => 3,
                'is_required' => true,
                'is_active' => true,
            ]
        );
    }
}
