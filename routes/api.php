<?php

use App\Http\Controllers\Auth\CreateCompanyController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\UpdateOnboardingController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Employee\ClockController;
use App\Http\Controllers\Employee\ShiftController as EmployeeShiftController;
use App\Http\Controllers\Manager\EmployeeController;
use App\Http\Controllers\Manager\LocationController;
use App\Http\Controllers\Manager\ShiftAssignmentController as ManagerShiftAssignmentController;
use App\Http\Controllers\Manager\ShiftController as ManagerShiftController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class);
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('user', UserController::class)->middleware('auth:sanctum');
    Route::post('company', CreateCompanyController::class)->middleware('auth:sanctum');
    Route::patch('onboarding', UpdateOnboardingController::class)->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('manager')->middleware('company.role:owner,admin')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::put('employees/{employee:id}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee:id}', [EmployeeController::class, 'destroy']);

        Route::get('shifts', [ManagerShiftController::class, 'index']);
        Route::post('shifts', [ManagerShiftController::class, 'store']);
        Route::put('shifts/{shift}', [ManagerShiftController::class, 'update']);
        Route::delete('shifts/{shift}', [ManagerShiftController::class, 'destroy']);
        Route::post('shifts/publish', [ManagerShiftController::class, 'publish']);

        Route::get('shift-assignments', [ManagerShiftAssignmentController::class, 'index']);
        Route::post('shift-assignments', [ManagerShiftAssignmentController::class, 'store']);
        Route::put('shift-assignments/{shiftAssignment}', [ManagerShiftAssignmentController::class, 'update']);
        Route::delete('shift-assignments/{shiftAssignment}', [ManagerShiftAssignmentController::class, 'destroy']);

        Route::get('locations', [LocationController::class, 'index']);
        Route::post('locations', [LocationController::class, 'store']);
    });

    Route::prefix('employee')->middleware('employee')->group(function () {
        Route::get('shifts', [EmployeeShiftController::class, 'index']);
        Route::post('clock-in', [ClockController::class, 'clockIn']);
        Route::post('clock-out', [ClockController::class, 'clockOut']);
    });
});
