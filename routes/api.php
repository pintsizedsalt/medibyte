<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WaterLogController;
use App\Http\Controllers\SleepLogController;
use App\Http\Controllers\WalkingLogController;
use App\Http\Controllers\MealLogController;
use App\Http\Controllers\WeightLogController;
use App\Http\Controllers\MoodLogController;
use App\Http\Controllers\PeriodLogController;
use App\Http\Controllers\SymptomLogController;
use App\Http\Controllers\VitalSignLogController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\DashboardController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'store']);
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
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::get('/progress', [ProgressController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

