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
use App\Http\Controllers\UserManagementController;
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
});
