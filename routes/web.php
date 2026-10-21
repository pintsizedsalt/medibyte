<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect('/dashboard') : redirect('/login'));

Route::view('/login', 'login')->name('login');
Route::view('/register', 'register');
Route::view('/forgot-password', 'forgot-password');
Route::get('/reset-password/{token}', fn (string $token) => view('reset-password', ['token' => $token]))->name('password.reset');
Route::view('/privacy', 'privacy');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard');
    Route::view('/records', 'records');
    Route::view('/profile', 'account-profile');
    Route::view('/profile-setup', 'profile');
    Route::view('/settings', 'settings');
    Route::view('/water', 'water');
    Route::view('/sleep', 'sleep');
    Route::view('/walking', 'walking');
    Route::view('/meals', 'meals');
    Route::view('/weight', 'weight');
    Route::view('/mood', 'mood');
    Route::view('/symptoms', 'symptoms');
    Route::view('/vitals', 'vitals');
    Route::view('/medications', 'medications');
    Route::view('/appointments', 'appointments');
    Route::view('/documents', 'documents');
    Route::view('/notifications', 'notifications');
    Route::get('/period', function () {
        abort_unless(auth()->user()?->show_period_tracker, 403);

        return view('period');
    });
});
