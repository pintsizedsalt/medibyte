<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/test', 'test');
Route::view('/login', 'login');
Route::view('/register', 'register');
Route::view('/dashboard', 'dashboard');
Route::view('/profile-setup', 'profile');
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
Route::view('/period', 'period');
