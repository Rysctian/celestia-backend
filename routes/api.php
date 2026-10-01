<?php

use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Schedule\ScheduleController;
use App\Http\Controllers\Schedule\EmployeeScheduleController;
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

    Route::get('/employees',               [EmployeeController::class, 'index']);
    Route::get('/employees/{employee_id}', [EmployeeController::class, 'find']);
    Route::post('/employees',              [EmployeeController::class, 'store'])->middleware('can:manage-employees');
    Route::put('/employees/{employee_id}', [EmployeeController::class, 'update'])->middleware('can:manage-employees');

    Route::middleware('can:manage-schedules')->group(function () {
        Route::get('/schedules',               [ScheduleController::class, 'index']);
        Route::get('/schedules/{schedule_id}', [ScheduleController::class, 'find'])->whereNumber('schedule_id');
        Route::post('/schedules',              [ScheduleController::class, 'store']);
        Route::put('/schedules/{schedule_id}', [ScheduleController::class, 'update'])->whereNumber('schedule_id');
        Route::post('/employee-schedules',      [EmployeeScheduleController::class, 'store']);
        Route::put('/employee-schedules/{assignment_id}', [EmployeeScheduleController::class, 'update'])->whereNumber('assignment_id');
    });

    Route::get('/employees/{employee_id}/schedules', [EmployeeScheduleController::class, 'index']);
    Route::get('/employees/{employee_id}/schedule', [EmployeeScheduleController::class, 'find']);
});