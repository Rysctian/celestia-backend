<?php
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthenticationController::class, 'store'])->name('login');

if (app()->environment('local')) {
    Route::post('/dev/token', [AuthenticationController::class, 'token']);
}


Route::middleware(['auth:sanctum'])->group(function(){
  Route::get("/employees",        [EmployeeController::class, "index"]);
  Route::get("/employees:{employeeid}",   [EmployeeController::class, "find"]);
  Route::post("/employees",       [EmployeeController::class, "store"]);
  Route::put('/employees/{employeeId}', [EmployeeController::class, 'update']);
});