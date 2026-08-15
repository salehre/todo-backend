<?php

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupMessageController;
use App\Http\Controllers\GroupInviteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
Broadcast::routes(['middleware' => ['auth:sanctum']]);

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
    Route::get('/groups/{group}/tasks', [TaskController::class, 'groupTasks']);
    Route::post('/groups/{group}/tasks', [TaskController::class, 'storeGroupTask']);
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{group}', [GroupController::class, 'show']);
    Route::put('/groups/{group}', [GroupController::class, 'update']);
    Route::delete('/groups/{group}', [GroupController::class, 'destroy']);
    Route::post('/groups/{group}/avatar', [GroupController::class, 'uploadAvatar']);
    Route::get('/groups/{group}/members', [GroupController::class, 'members']);
    Route::delete('/groups/{group}/members/{userId}', [GroupController::class, 'removeMember']);
    Route::put('/groups/{group}/members/{userId}/role', [GroupController::class, 'updateRole']);
    Route::get('/invites', [GroupInviteController::class, 'index']);
    Route::post('/groups/{group}/invites', [GroupInviteController::class, 'store']);
    Route::put('/invites/{invite}/accept', [GroupInviteController::class, 'accept']);
    Route::put('/invites/{invite}/decline', [GroupInviteController::class, 'decline']);
    Route::get('/groups/{group}/messages', [GroupMessageController::class, 'index']);
    Route::post('/groups/{group}/messages', [GroupMessageController::class, 'store']);
    Route::put('/groups/{group}/messages/{message}', [GroupMessageController::class, 'update']);
    Route::delete('/groups/{group}/messages/{message}', [GroupMessageController::class, 'destroy']);
    Route::put('/groups/{group}/messages/{message}/pin', [GroupMessageController::class, 'togglePin']);
    Route::get('/groups/{group}/pinned-messages', [GroupMessageController::class, 'pinnedMessages']);
    Route::post('/groups/{group}/messages/{message}/react', [GroupMessageController::class, 'react']);
    Route::post('/groups/{group}/typing', [GroupMessageController::class, 'typing']);
    Route::put('/groups/{group}/read', [GroupMessageController::class, 'markRead']);
    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users/{id}/profile', [UserController::class, 'showProfile']);
});
