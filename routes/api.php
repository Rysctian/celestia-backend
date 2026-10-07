<?php

use App\Http\Controllers\Attendance\EmployeeAttendanceController;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PayrollCutoffController;
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

    Route::get('/payroll-cutoffs', [PayrollCutoffController::class, 'index'])->middleware('can:payroll_cutoff,view');
    Route::get('/payroll-cutoffs/{payroll_cutoff_id}', [PayrollCutoffController::class, 'find'])->whereNumber('payroll_cutoff_id')->middleware('can:payroll_cutoff,view');
    Route::post('/payroll-cutoffs', [PayrollCutoffController::class, 'store'])->middleware('can:payroll_cutoff,create');
    Route::put('/payroll-cutoffs/{payroll_cutoff_id}', [PayrollCutoffController::class, 'update'])->whereNumber('payroll_cutoff_id')->middleware('can:payroll_cutoff,update');
    Route::delete('/payroll-cutoffs/{payroll_cutoff_id}', [PayrollCutoffController::class, 'destroy'])->whereNumber('payroll_cutoff_id')->middleware('can:payroll_cutoff,destroy');
});
