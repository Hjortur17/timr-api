<?php

use App\Http\Controllers\Auth\CreateCompanyController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAccountController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\UpdateOnboardingController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Employee\ClockController;
use App\Http\Controllers\Employee\NotificationPreferenceController;
use App\Http\Controllers\Employee\ShiftController as EmployeeShiftController;
use App\Http\Controllers\Manager\EmployeeController;
use App\Http\Controllers\Manager\LocationController;
use App\Http\Controllers\Manager\ShiftAssignmentController as ManagerShiftAssignmentController;
use App\Http\Controllers\Manager\ShiftController as ManagerShiftController;
use App\Http\Controllers\Manager\ShiftTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class);
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('user', UserController::class)->middleware('auth:sanctum');
    Route::post('company', CreateCompanyController::class)->middleware('auth:sanctum');
    Route::patch('onboarding', UpdateOnboardingController::class)->middleware('auth:sanctum');

    Route::get('redirect/{provider}', [SocialAuthController::class, 'redirect']);
    Route::get('callback/{provider}', [SocialAuthController::class, 'callback']);
    Route::post('social/{provider}', [SocialAuthController::class, 'token']);
    Route::get('social-accounts', [SocialAccountController::class, 'index'])->middleware('auth:sanctum');
    Route::delete('social-accounts/{socialAccount}', [SocialAccountController::class, 'destroy'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('manager')->middleware('company.role:owner,admin')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::put('employees/{employee:id}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee:id}', [EmployeeController::class, 'destroy']);
        Route::post('employees/{employee:id}/invite', [EmployeeController::class, 'sendInvite']);

        Route::get('shifts', [ManagerShiftController::class, 'index']);
        Route::post('shifts', [ManagerShiftController::class, 'store']);
        Route::put('shifts/{shift}', [ManagerShiftController::class, 'update']);
        Route::get('shifts/{shift}/deletion-preview', [ManagerShiftController::class, 'deletionPreview']);
        Route::delete('shifts/{shift}', [ManagerShiftController::class, 'destroy']);
        Route::post('shifts/publish', [ManagerShiftController::class, 'publish']);
        Route::post('shifts/unpublish', [ManagerShiftController::class, 'unpublish']);

        Route::get('shift-assignments', [ManagerShiftAssignmentController::class, 'index']);
        Route::post('shift-assignments', [ManagerShiftAssignmentController::class, 'store']);
        Route::put('shift-assignments/{shiftAssignment}', [ManagerShiftAssignmentController::class, 'update']);
        Route::delete('shift-assignments/{shiftAssignment}', [ManagerShiftAssignmentController::class, 'destroy']);

        Route::get('locations', [LocationController::class, 'index']);
        Route::post('locations', [LocationController::class, 'store']);

        Route::get('shift-templates', [ShiftTemplateController::class, 'index']);
        Route::post('shift-templates', [ShiftTemplateController::class, 'store']);
        Route::put('shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'update']);
        Route::delete('shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'destroy']);
        Route::post('shift-templates/{shiftTemplate}/generate', [ShiftTemplateController::class, 'generate']);
    });

    Route::prefix('employee')->middleware('employee')->group(function () {
        Route::get('shifts', [EmployeeShiftController::class, 'index']);
        Route::post('clock-in', [ClockController::class, 'clockIn']);
        Route::post('clock-out', [ClockController::class, 'clockOut']);
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'index']);
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);
    });
});
