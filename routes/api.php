<?php

use App\Http\Controllers\Attendance\AttendancePolicyController;
use App\Http\Controllers\Attendance\EmployeeAttendanceController;
use App\Http\Controllers\Attendance\EmployeeLogController;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\CutOffController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OnlineApplication\ApproverSetupController;
use App\Http\Controllers\OnlineApplication\CorrectionApplicationController;
use App\Http\Controllers\OnlineApplication\LeaveApplicationController;
use App\Http\Controllers\OnlineApplication\LeavePolicyController;
use App\Http\Controllers\OnlineApplication\LeaveSetupController;
use App\Http\Controllers\OnlineApplication\OfficialBusinessApplicationController;
use App\Http\Controllers\OnlineApplication\OvertimeApplicationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Schedule\EmployeeScheduleController;
use App\Http\Controllers\Schedule\ScheduleController;
use App\Http\Controllers\Setup\ClearanceSetupController;
use App\Http\Controllers\Setup\OrganizationalSetupController;
use App\Http\Controllers\Setup\SetupReferenceController;
use App\Http\Controllers\UserManagementController;
use App\Models\Setup\SetupReferenceItem;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::post('/login', [AuthenticationController::class, 'store'])
    ->middleware('web')
    ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
    ->name('login');

if (app()->environment(['local', 'testing'])) {
    Route::post('/dev/token', [AuthenticationController::class, 'token'])
        ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class);
}

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthenticationController::class, 'destroy'])->name('logout');
    Route::get('/me', [AuthenticationController::class, 'me'])->name('me');
    Route::get('/me/menus', [MenuController::class, 'mine']);

    Route::get('/menus', [MenuController::class, 'index'])->middleware('can:user_management,view');
    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:user_management,view');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:user_management,create');
    Route::delete('/roles/{role_id}', [RoleController::class, 'destroy'])->middleware('can:user_management,destroy');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('can:user_management,update');
    Route::get('/roles/{role}/access', [RoleController::class, 'access'])->middleware('can:user_management,view');
    Route::put('/roles/{role}/access', [RoleController::class, 'updateAccess'])->middleware('can:user_management,update');

    Route::get('/users', [UserManagementController::class, 'index'])->middleware('can:user_management,view');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->middleware('can:user_management,view');
    Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole'])->middleware('can:user_management,update');

    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('can:employee_201,view');
    Route::get('/employees/{employee_id}', [EmployeeController::class, 'find'])->middleware('can:employee_201,view');
    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('can:employee_201,create');
    Route::put('/employees/{employee_id}', [EmployeeController::class, 'update'])->middleware('can:employee_201,update');

    Route::get('/schedules', [ScheduleController::class, 'index'])->middleware('can:schedule_list,view');
    Route::get('/schedules/{schedule_id}', [ScheduleController::class, 'find'])->whereNumber('schedule_id')->middleware('can:schedule_list,view');
    Route::post('/schedules', [ScheduleController::class, 'store'])->middleware('can:schedule_list,create');
    Route::put('/schedules/{schedule_id}', [ScheduleController::class, 'update'])->whereNumber('schedule_id')->middleware('can:schedule_list,update');
    Route::post('/employee-schedules', [EmployeeScheduleController::class, 'store'])->middleware('can:schedule_list,create');
    Route::put('/employee-schedules/{assignment_id}', [EmployeeScheduleController::class, 'update'])->whereNumber('assignment_id')->middleware('can:schedule_list,update');

    Route::get('/employees/{employee_id}/schedules', [EmployeeScheduleController::class, 'index']);
    Route::get('/employees/{employee_id}/schedule', [EmployeeScheduleController::class, 'find']);

    Route::get('/employee-attendance', [EmployeeAttendanceController::class, 'showAttendance'])
        ->middleware('can:attendance,view');

    Route::get('/employee-logs', [EmployeeLogController::class, 'index'])
        ->middleware('can:attendance,view');
    Route::post('/employee-logs', [EmployeeLogController::class, 'store'])
        ->middleware('can:attendance,create');
    Route::get('/employee-logs/template', [EmployeeLogController::class, 'downloadTemplate'])
        ->middleware('can:attendance,view');
    Route::post('/employee-logs/import', [EmployeeLogController::class, 'import'])
        ->middleware('can:attendance,create');

    Route::get('/attendance-policies', [AttendancePolicyController::class, 'index'])->middleware('can:attendance_policy,view');
    Route::get('/attendance-policies/{policy_id}', [AttendancePolicyController::class, 'find'])->whereNumber('policy_id')->middleware('can:attendance_policy,view');
    Route::post('/attendance-policies', [AttendancePolicyController::class, 'store'])->middleware('can:attendance_policy,create');
    Route::put('/attendance-policies/{policy_id}', [AttendancePolicyController::class, 'update'])->whereNumber('policy_id')->middleware('can:attendance_policy,update');
    Route::delete('/attendance-policies/{policy_id}', [AttendancePolicyController::class, 'destroy'])->whereNumber('policy_id')->middleware('can:attendance_policy,delete');

    Route::get('/cut-offs', [CutOffController::class, 'index'])->middleware('can:cut_off,view');
    Route::get('/cut-offs/{cut_off_id}', [CutOffController::class, 'find'])->whereNumber('cut_off_id')->middleware('can:cut_off,view');
    Route::post('/cut-offs', [CutOffController::class, 'store'])->middleware('can:cut_off,create');
    Route::put('/cut-offs/{cut_off_id}', [CutOffController::class, 'update'])->whereNumber('cut_off_id')->middleware('can:cut_off,update');
    Route::delete('/cut-offs/{cut_off_id}', [CutOffController::class, 'destroy'])->whereNumber('cut_off_id')->middleware('can:cut_off,delete');

    Route::get('/approver-setups', [ApproverSetupController::class, 'index'])->middleware('can:approver_setup,view');
    Route::get('/approver-setups/{employee_id}/{application_type}', [ApproverSetupController::class, 'find'])->middleware('can:approver_setup,view');
    Route::put('/approver-setups', [ApproverSetupController::class, 'update'])->middleware('can:approver_setup,update');

    Route::get('/overtime-applications', [OvertimeApplicationController::class, 'index'])->middleware('can:overtime_application,view');
    Route::get('/overtime-applications/{application_id}', [OvertimeApplicationController::class, 'find'])->whereNumber('application_id')->middleware('can:overtime_application,view');
    Route::post('/overtime-applications', [OvertimeApplicationController::class, 'store'])->middleware('can:overtime_application,create');
    Route::post('/overtime-applications/{application_id}/approve', [OvertimeApplicationController::class, 'approve'])->whereNumber('application_id')->middleware('can:overtime_application,update');
    Route::post('/overtime-applications/{application_id}/disapprove', [OvertimeApplicationController::class, 'disapprove'])->whereNumber('application_id')->middleware('can:overtime_application,update');
    Route::post('/overtime-applications/{application_id}/cancel', [OvertimeApplicationController::class, 'cancel'])->whereNumber('application_id')->middleware('can:overtime_application,update');

    Route::get('/official-business-applications', [OfficialBusinessApplicationController::class, 'index'])->middleware('can:official_business,view');
    Route::get('/official-business-applications/{application_id}', [OfficialBusinessApplicationController::class, 'find'])->whereNumber('application_id')->middleware('can:official_business,view');
    Route::post('/official-business-applications', [OfficialBusinessApplicationController::class, 'store'])->middleware('can:official_business,create');
    Route::post('/official-business-applications/{application_id}/approve', [OfficialBusinessApplicationController::class, 'approve'])->whereNumber('application_id')->middleware('can:official_business,update');
    Route::post('/official-business-applications/{application_id}/disapprove', [OfficialBusinessApplicationController::class, 'disapprove'])->whereNumber('application_id')->middleware('can:official_business,update');
    Route::post('/official-business-applications/{application_id}/cancel', [OfficialBusinessApplicationController::class, 'cancel'])->whereNumber('application_id')->middleware('can:official_business,update');

    Route::get('/correction-applications', [CorrectionApplicationController::class, 'index'])->middleware('can:correction_application,view');
    Route::get('/correction-applications/logs', [CorrectionApplicationController::class, 'logs'])->middleware('can:correction_application,view');
    Route::get('/correction-applications/{application_id}', [CorrectionApplicationController::class, 'find'])->whereNumber('application_id')->middleware('can:correction_application,view');
    Route::post('/correction-applications', [CorrectionApplicationController::class, 'store'])->middleware('can:correction_application,create');
    Route::post('/correction-applications/{application_id}/approve', [CorrectionApplicationController::class, 'approve'])->whereNumber('application_id')->middleware('can:correction_application,update');
    Route::post('/correction-applications/{application_id}/disapprove', [CorrectionApplicationController::class, 'disapprove'])->whereNumber('application_id')->middleware('can:correction_application,update');
    Route::post('/correction-applications/{application_id}/cancel', [CorrectionApplicationController::class, 'cancel'])->whereNumber('application_id')->middleware('can:correction_application,update');

    Route::get('/leave-policies', [LeavePolicyController::class, 'index'])->middleware('can:leave_application,view');
    Route::get('/leave-policies/{policy_id}', [LeavePolicyController::class, 'find'])->whereNumber('policy_id')->middleware('can:leave_application,view');
    Route::post('/leave-policies', [LeavePolicyController::class, 'store'])->middleware('can:leave_application,create');
    Route::put('/leave-policies/{policy_id}', [LeavePolicyController::class, 'update'])->whereNumber('policy_id')->middleware('can:leave_application,update');
    Route::delete('/leave-policies/{policy_id}', [LeavePolicyController::class, 'destroy'])->whereNumber('policy_id')->middleware('can:leave_application,delete');

    Route::get('/leave-types', [LeaveSetupController::class, 'types'])->middleware('can:leave_application,view');
    Route::post('/leave-types', [LeaveSetupController::class, 'storeType'])->middleware('can:leave_application,create');
    Route::put('/leave-types/{leave_type_id}', [LeaveSetupController::class, 'updateType'])->whereNumber('leave_type_id')->middleware('can:leave_application,update');
    Route::delete('/leave-types/{leave_type_id}', [LeaveSetupController::class, 'destroyType'])->whereNumber('leave_type_id')->middleware('can:leave_application,delete');

    Route::get('/employee-leave-credits', [LeaveSetupController::class, 'credits'])->middleware('can:leave_application,view');
    Route::post('/employee-leave-credits', [LeaveSetupController::class, 'storeCredit'])->middleware('can:leave_application,create');
    Route::put('/employee-leave-credits/{credit_id}', [LeaveSetupController::class, 'updateCredit'])->whereNumber('credit_id')->middleware('can:leave_application,update');
    Route::post('/employee-leave-credits/allocate', [LeaveSetupController::class, 'allocateCredits'])->middleware('can:leave_application,create');
    Route::get('/employee-leave-credits/{credit_id}/logs', [LeaveSetupController::class, 'creditLogs'])->whereNumber('credit_id')->middleware('can:leave_application,view');

    Route::get('/leave-applications', [LeaveApplicationController::class, 'index'])->middleware('can:leave_application,view');
    Route::get('/leave-applications/balances', [LeaveApplicationController::class, 'balances'])->middleware('can:leave_application,view');
    Route::get('/leave-applications/{application_id}', [LeaveApplicationController::class, 'find'])->whereNumber('application_id')->middleware('can:leave_application,view');
    Route::get('/leave-applications/{application_id}/attachment', [LeaveApplicationController::class, 'attachment'])->whereNumber('application_id')->middleware('can:leave_application,view');
    Route::post('/leave-applications', [LeaveApplicationController::class, 'store'])->middleware('can:leave_application,create');
    Route::post('/leave-applications/{application_id}/approve', [LeaveApplicationController::class, 'approve'])->whereNumber('application_id')->middleware('can:leave_application,update');
    Route::post('/leave-applications/{application_id}/disapprove', [LeaveApplicationController::class, 'disapprove'])->whereNumber('application_id')->middleware('can:leave_application,update');
    Route::post('/leave-applications/{application_id}/cancel', [LeaveApplicationController::class, 'cancel'])->whereNumber('application_id')->middleware('can:leave_application,update');

    // -------------------------------------------------------------------------
    // HRIS Setup Modules (01. Organizational, 02. Employee Info, 03. Employment, 04. Administrative)
    // -------------------------------------------------------------------------
    Route::get('/setup/options', [SetupReferenceController::class, 'options']);
    Route::get('/setup/entity-options', [SetupReferenceController::class, 'entityOptions']);

    // 01. Organizational Setup
    Route::get('/setup/organizational/tree', [OrganizationalSetupController::class, 'tree'])->middleware('can:organizational_setup,view');

    Route::get('/setup/offices', [OrganizationalSetupController::class, 'indexOffices'])->middleware('can:organizational_setup,view');
    Route::get('/setup/offices/{office_id}', [OrganizationalSetupController::class, 'findOffice'])->whereNumber('office_id')->middleware('can:organizational_setup,view');
    Route::post('/setup/offices', [OrganizationalSetupController::class, 'storeOffice'])->middleware('can:organizational_setup,create');
    Route::put('/setup/offices/{office_id}', [OrganizationalSetupController::class, 'updateOffice'])->whereNumber('office_id')->middleware('can:organizational_setup,update');
    Route::delete('/setup/offices/{office_id}', [OrganizationalSetupController::class, 'destroyOffice'])->whereNumber('office_id')->middleware('can:organizational_setup,delete');

    Route::get('/setup/departments', [OrganizationalSetupController::class, 'indexDepartments'])->middleware('can:organizational_setup,view');
    Route::get('/setup/departments/{department_id}', [OrganizationalSetupController::class, 'findDepartment'])->whereNumber('department_id')->middleware('can:organizational_setup,view');
    Route::post('/setup/departments', [OrganizationalSetupController::class, 'storeDepartment'])->middleware('can:organizational_setup,create');
    Route::put('/setup/departments/{department_id}', [OrganizationalSetupController::class, 'updateDepartment'])->whereNumber('department_id')->middleware('can:organizational_setup,update');
    Route::delete('/setup/departments/{department_id}', [OrganizationalSetupController::class, 'destroyDepartment'])->whereNumber('department_id')->middleware('can:organizational_setup,delete');

    Route::get('/setup/divisions', [OrganizationalSetupController::class, 'indexDivisions'])->middleware('can:organizational_setup,view');
    Route::get('/setup/divisions/{division_id}', [OrganizationalSetupController::class, 'findDivision'])->whereNumber('division_id')->middleware('can:organizational_setup,view');
    Route::post('/setup/divisions', [OrganizationalSetupController::class, 'storeDivision'])->middleware('can:organizational_setup,create');
    Route::put('/setup/divisions/{division_id}', [OrganizationalSetupController::class, 'updateDivision'])->whereNumber('division_id')->middleware('can:organizational_setup,update');
    Route::delete('/setup/divisions/{division_id}', [OrganizationalSetupController::class, 'destroyDivision'])->whereNumber('division_id')->middleware('can:organizational_setup,delete');

    Route::get('/setup/sections', [OrganizationalSetupController::class, 'indexSections'])->middleware('can:organizational_setup,view');
    Route::get('/setup/sections/{section_id}', [OrganizationalSetupController::class, 'findSection'])->whereNumber('section_id')->middleware('can:organizational_setup,view');
    Route::post('/setup/sections', [OrganizationalSetupController::class, 'storeSection'])->middleware('can:organizational_setup,create');
    Route::put('/setup/sections/{section_id}', [OrganizationalSetupController::class, 'updateSection'])->whereNumber('section_id')->middleware('can:organizational_setup,update');
    Route::delete('/setup/sections/{section_id}', [OrganizationalSetupController::class, 'destroySection'])->whereNumber('section_id')->middleware('can:organizational_setup,delete');

    Route::get('/setup/positions', [OrganizationalSetupController::class, 'indexPositions'])->middleware('can:organizational_setup,view');
    Route::get('/setup/positions/{position_id}', [OrganizationalSetupController::class, 'findPosition'])->whereNumber('position_id')->middleware('can:organizational_setup,view');
    Route::post('/setup/positions', [OrganizationalSetupController::class, 'storePosition'])->middleware('can:organizational_setup,create');
    Route::put('/setup/positions/{position_id}', [OrganizationalSetupController::class, 'updatePosition'])->whereNumber('position_id')->middleware('can:organizational_setup,update');
    Route::delete('/setup/positions/{position_id}', [OrganizationalSetupController::class, 'destroyPosition'])->whereNumber('position_id')->middleware('can:organizational_setup,delete');

    Route::get('/setup/reports-items', [OrganizationalSetupController::class, 'indexReportsItems'])->middleware('can:organizational_setup,view');
    Route::get('/setup/reports-items/{reports_item_id}', [OrganizationalSetupController::class, 'findReportsItem'])->whereNumber('reports_item_id')->middleware('can:organizational_setup,view');
    Route::post('/setup/reports-items', [OrganizationalSetupController::class, 'storeReportsItem'])->middleware('can:organizational_setup,create');
    Route::put('/setup/reports-items/{reports_item_id}', [OrganizationalSetupController::class, 'updateReportsItem'])->whereNumber('reports_item_id')->middleware('can:organizational_setup,update');
    Route::delete('/setup/reports-items/{reports_item_id}', [OrganizationalSetupController::class, 'destroyReportsItem'])->whereNumber('reports_item_id')->middleware('can:organizational_setup,delete');

    // 02. Employee Information Setup (Reference Items)
    Route::get('/setup/employee-information/categories', [SetupReferenceController::class, 'categories'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYEE_INFO)
        ->middleware('can:employee_info_setup,view');
    Route::get('/setup/employee-information/items', [SetupReferenceController::class, 'index'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYEE_INFO)
        ->middleware('can:employee_info_setup,view');
    Route::get('/setup/employee-information/items/{item_id}', [SetupReferenceController::class, 'find'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYEE_INFO)
        ->middleware('can:employee_info_setup,view');
    Route::post('/setup/employee-information/items', [SetupReferenceController::class, 'store'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYEE_INFO)
        ->middleware('can:employee_info_setup,create');
    Route::put('/setup/employee-information/items/{item_id}', [SetupReferenceController::class, 'update'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYEE_INFO)
        ->middleware('can:employee_info_setup,update');
    Route::delete('/setup/employee-information/items/{item_id}', [SetupReferenceController::class, 'destroy'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYEE_INFO)
        ->middleware('can:employee_info_setup,delete');

    // 03. Employment Setup (Reference Items)
    Route::get('/setup/employment/categories', [SetupReferenceController::class, 'categories'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYMENT)
        ->middleware('can:employment_setup,view');
    Route::get('/setup/employment/items', [SetupReferenceController::class, 'index'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYMENT)
        ->middleware('can:employment_setup,view');
    Route::get('/setup/employment/items/{item_id}', [SetupReferenceController::class, 'find'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYMENT)
        ->middleware('can:employment_setup,view');
    Route::post('/setup/employment/items', [SetupReferenceController::class, 'store'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYMENT)
        ->middleware('can:employment_setup,create');
    Route::put('/setup/employment/items/{item_id}', [SetupReferenceController::class, 'update'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYMENT)
        ->middleware('can:employment_setup,update');
    Route::delete('/setup/employment/items/{item_id}', [SetupReferenceController::class, 'destroy'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_EMPLOYMENT)
        ->middleware('can:employment_setup,delete');

    // 04. Administrative Setup (Reference Items + Clearance Relational Setup)
    Route::get('/setup/administrative/categories', [SetupReferenceController::class, 'categories'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_ADMINISTRATIVE)
        ->middleware('can:administrative_setup,view');
    Route::get('/setup/administrative/items', [SetupReferenceController::class, 'index'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_ADMINISTRATIVE)
        ->middleware('can:administrative_setup,view');
    Route::get('/setup/administrative/items/{item_id}', [SetupReferenceController::class, 'find'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_ADMINISTRATIVE)
        ->middleware('can:administrative_setup,view');
    Route::post('/setup/administrative/items', [SetupReferenceController::class, 'store'])
        ->defaults('setup_group', SetupReferenceItem::GROUP_ADMINISTRATIVE)
        ->middleware('can:administrative_setup,create');
    Route::put('/setup/administrative/items/{item_id}', [SetupReferenceController::class, 'update'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_ADMINISTRATIVE)
        ->middleware('can:administrative_setup,update');
    Route::delete('/setup/administrative/items/{item_id}', [SetupReferenceController::class, 'destroy'])
        ->whereNumber('item_id')
        ->defaults('setup_group', SetupReferenceItem::GROUP_ADMINISTRATIVE)
        ->middleware('can:administrative_setup,delete');

    Route::get('/setup/clearance-types', [ClearanceSetupController::class, 'indexTypes'])->middleware('can:administrative_setup,view');
    Route::get('/setup/clearance-types/{clearance_type_id}', [ClearanceSetupController::class, 'findType'])->whereNumber('clearance_type_id')->middleware('can:administrative_setup,view');
    Route::post('/setup/clearance-types', [ClearanceSetupController::class, 'storeType'])->middleware('can:administrative_setup,create');
    Route::put('/setup/clearance-types/{clearance_type_id}', [ClearanceSetupController::class, 'updateType'])->whereNumber('clearance_type_id')->middleware('can:administrative_setup,update');
    Route::delete('/setup/clearance-types/{clearance_type_id}', [ClearanceSetupController::class, 'destroyType'])->whereNumber('clearance_type_id')->middleware('can:administrative_setup,delete');

    Route::get('/setup/clearance-requirements', [ClearanceSetupController::class, 'indexRequirements'])->middleware('can:administrative_setup,view');
    Route::get('/setup/clearance-requirements/{requirement_id}', [ClearanceSetupController::class, 'findRequirement'])->whereNumber('requirement_id')->middleware('can:administrative_setup,view');
    Route::post('/setup/clearance-requirements', [ClearanceSetupController::class, 'storeRequirement'])->middleware('can:administrative_setup,create');
    Route::put('/setup/clearance-requirements/{requirement_id}', [ClearanceSetupController::class, 'updateRequirement'])->whereNumber('requirement_id')->middleware('can:administrative_setup,update');
    Route::delete('/setup/clearance-requirements/{requirement_id}', [ClearanceSetupController::class, 'destroyRequirement'])->whereNumber('requirement_id')->middleware('can:administrative_setup,delete');

    Route::get('/setup/clearance-signatories', [ClearanceSetupController::class, 'indexSignatories'])->middleware('can:administrative_setup,view');
    Route::get('/setup/clearance-signatories/{signatory_id}', [ClearanceSetupController::class, 'findSignatory'])->whereNumber('signatory_id')->middleware('can:administrative_setup,view');
    Route::post('/setup/clearance-signatories', [ClearanceSetupController::class, 'storeSignatory'])->middleware('can:administrative_setup,create');
    Route::put('/setup/clearance-signatories/{signatory_id}', [ClearanceSetupController::class, 'updateSignatory'])->whereNumber('signatory_id')->middleware('can:administrative_setup,update');
    Route::delete('/setup/clearance-signatories/{signatory_id}', [ClearanceSetupController::class, 'destroySignatory'])->whereNumber('signatory_id')->middleware('can:administrative_setup,delete');
});
