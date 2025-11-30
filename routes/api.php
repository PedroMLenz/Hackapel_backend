<?php

use App\Http\Controllers\FilterController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;

Route::post('/login', action: [AuthController::class, 'login']);
Route::post('/users/{id}/notifications', [InformationController::class, 'store'])->middleware('auth:sanctum');
Route::get('/users/{id}/notifications', [InformationController::class, 'indexByUser'])->middleware('auth:sanctum');
Route::get('/filters', [FilterController::class, 'index'])->middleware('auth:sanctum');
Route::post('/telegram/webhook', [InformationController::class, 'handleTelegramWebhook']);
Route::post('/users/{id}/informations', [UserController::class, 'store'])->middleware('auth:sanctum');
Route::put('/users/{id}/informations', [UserController::class, 'update'])->middleware('auth:sanctum');
Route::get('/users/{id}/informations', [UserController::class, 'index'])->middleware('auth:sanctum');
