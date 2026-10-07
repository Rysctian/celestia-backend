<?php

use App\Http\Controllers\Attendance\AttendanceCutoffController;
use App\Http\Controllers\Attendance\EmployeeAttendanceController;
use App\Http\Controllers\Attendance\EmployeeLogController;
use App\Http\Controllers\Attendance\TimesheetController;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MenuController;
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

if (app()->environment('local')) {
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
    Route::delete('/roles/{role_id}', [RoleController::class, 'delete'])->middleware('can:user_management,delete');
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

    Route::get('/employee-logs', [EmployeeLogController::class, 'index']);
    Route::post('/employee-logs', [EmployeeLogController::class, 'store'])->middleware('can:attendance,create');
    Route::get('/timesheets', [TimesheetController::class, 'index']);
    Route::get('/employee-attendance', [EmployeeAttendanceController::class, 'index']);
    Route::post('/employee-attendance/process', [EmployeeAttendanceController::class, 'process'])->middleware('can:attendance,update');
    Route::post('/employee-attendance/{attendance_id}/corrections', [EmployeeAttendanceController::class, 'correct'])->whereNumber('attendance_id')->middleware('can:attendance,update');
    Route::post('/attendance-exceptions', [EmployeeAttendanceController::class, 'exception'])->middleware('can:attendance,update');
    Route::post('/attendance-overtime/{overtime_id}/approve', [EmployeeAttendanceController::class, 'approveOvertime'])->whereNumber('overtime_id')->middleware('can:attendance,update');
    Route::get('/attendance-cutoffs', [AttendanceCutoffController::class, 'index']);
    Route::get('/attendance-cutoffs/{cutoff_id}', [AttendanceCutoffController::class, 'find'])->whereNumber('cutoff_id');
    Route::post('/attendance-cutoffs', [AttendanceCutoffController::class, 'store'])->middleware('can:attendance,create')->name('attendance-cutoffs.store');
    Route::post('/attendance-cutoffs/{cutoff_id}/confirm', [AttendanceCutoffController::class, 'confirm'])->whereNumber('cutoff_id')->middleware('can:attendance,update')->name('attendance-cutoffs.confirm');
    Route::post('/attendance-cutoffs/{cutoff_id}/reopen', [AttendanceCutoffController::class, 'reopen'])->whereNumber('cutoff_id')->middleware('can:attendance,update')->name('attendance-cutoffs.reopen');
});
