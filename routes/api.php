<?php

use App\Http\Controllers\FilterController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InformationController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/users/{id}/informations', [InformationController::class, 'store'])->middleware('auth:sanctum');
Route::get('/users/{id}/informations', [InformationController::class, 'indexByUser'])->middleware('auth:sanctum');
Route::get('/filters', [FilterController::class, 'index'])->middleware('auth:sanctum');
Route::post('/telegram/webhook', [InformationController::class, 'handleTelegramWebhook']);
