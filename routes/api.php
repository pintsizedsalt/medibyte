<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MealLogController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\MoodLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PeriodLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SleepLogController;
use App\Http\Controllers\SymptomLogController;
use App\Http\Controllers\VitalSignLogController;
use App\Http\Controllers\WalkingLogController;
use App\Http\Controllers\WaterLogController;
use App\Http\Controllers\WeightLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes. Throttled to slow down credential stuffing / spam sign-ups.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::get('/username-available', [AuthController::class, 'checkUsername']);
    });

    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::get('/user', fn (Request $request) => $request->user());

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile', [ProfileController::class, 'store']);
        Route::post('/profile/complete', [ProfileController::class, 'complete']);

        Route::get('/account/export', [AccountController::class, 'export']);
        Route::delete('/account', [AccountController::class, 'destroy']);

        Route::get('/water-logs', [WaterLogController::class, 'index']);
        Route::post('/water-logs', [WaterLogController::class, 'store']);
        Route::put('/water-logs/{id}', [WaterLogController::class, 'update']);
        Route::delete('/water-logs/{id}', [WaterLogController::class, 'destroy']);

        Route::apiResource('sleep-logs', SleepLogController::class)->except('show');
        Route::apiResource('walking-logs', WalkingLogController::class)->except('show');
        Route::apiResource('meal-logs', MealLogController::class)->except('show');
        Route::apiResource('weight-logs', WeightLogController::class)->except('show');

        Route::get('/moods', [MoodLogController::class, 'moods']);
        Route::apiResource('mood-logs', MoodLogController::class)->except('show');

        Route::get('/period-predict', [PeriodLogController::class, 'predict']);
        Route::apiResource('period-logs', PeriodLogController::class)->except('show');

        Route::apiResource('symptom-logs', SymptomLogController::class)->except('show');
        Route::apiResource('vital-sign-logs', VitalSignLogController::class)->except('show');
        Route::apiResource('medications', MedicationController::class)->except('show');
        Route::apiResource('appointments', AppointmentController::class)->except('show');

        Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
        Route::apiResource('documents', DocumentController::class)->except(['show', 'update']);

        Route::get('/reminders', [ReminderController::class, 'index']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::get('/progress', [ProgressController::class, 'index']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });
});
