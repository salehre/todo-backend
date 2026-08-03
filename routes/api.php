<?php

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/GInstall', function () {
    Artisan::call('migrate', ['--path' => 'database/migrations']);
    echo "Migrated todolist<br>";
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/userInfo', [AuthController::class, 'userInfo']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/resend-code', [AuthController::class, 'resendCode']);
Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/auth/set-password', [AuthController::class, 'setPassword']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/auth/preferences', [AuthController::class, 'updatePreferences']);
    Route::post('/feedback', [FeedbackController::class, 'store']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/avatar', [AuthController::class, 'uploadAvatar']);
    Route::post('/auth/cover', [AuthController::class, 'uploadCover']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks/create', [TaskController::class, 'store']);
    Route::put('/tasks/updateTask', [TaskController::class, 'updateTask']);
    Route::put('/tasks/updateStep', [TaskController::class, 'updateStep']);
    Route::delete('/tasks/delete', [TaskController::class, 'destroy']);
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{group}/messages', [GroupMessageController::class, 'index']);
    Route::post('/groups/{group}/messages', [GroupMessageController::class, 'store']);
    Route::put('/groups/{group}/messages/{message}', [GroupMessageController::class, 'update']);
    Route::delete('/groups/{group}/messages/{message}', [GroupMessageController::class, 'destroy']);
    Route::put('/groups/{group}/messages/{message}/pin', [GroupMessageController::class, 'togglePin']);
    Route::post('/groups/{group}/messages/{message}/react', [GroupMessageController::class, 'react']);
});
