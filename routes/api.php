<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupMessageController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ResourcesController;
use App\Http\Controllers\Api\V1\TherapistController;
use \App\Http\Controllers\Api\V1\AIChatController;
use App\Http\Controllers\Api\V1\UserStatusController;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\Auth\Logout;


Route::post('login', Login::class);
Route::post('register', Register::class);

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::post('logout', Logout::class);
    
    Route::prefix('v1')->group(function () {
        Route::apiResource('ai-messages', AIChatController::class);
        Route::apiResource('appointments', AppointmentController::class);
        Route::apiResource('conversations', ConversationController::class);
        
        // Mark conversation as read
        Route::post('conversations/{id}/mark-read', [ConversationController::class, 'markAsRead']);
        
        Route::apiResource('groups', GroupController::class);
        
        // Group join/leave routes
        Route::post('groups/{id}/join', [GroupController::class, 'join']);
        Route::post('groups/{id}/leave', [GroupController::class, 'leave']);
        
        // Mark group as read
        Route::post('groups/{id}/mark-read', [GroupController::class, 'markAsRead']);
        
        // Group messages routes
        Route::get('groups/{groupId}/messages', [GroupMessageController::class, 'index']);
        Route::post('groups/{groupId}/messages', [GroupMessageController::class, 'store']);
        Route::delete('groups/{groupId}/messages/{messageId}', [GroupMessageController::class, 'destroy']);
        
        Route::apiResource('messages', MessageController::class);
        
        // Notification routes with custom endpoints
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::put('notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
        Route::apiResource('notifications', NotificationController::class);
        
        Route::apiResource('profiles', ProfileController::class);
        Route::apiResource('reports', ReportController::class);
        Route::apiResource('resources', ResourcesController::class);
        Route::apiResource('therapists', TherapistController::class);
        
        // User status/presence routes
        Route::post('user-status', [UserStatusController::class, 'updateStatus']);
        Route::get('online-users', [UserStatusController::class, 'getOnlineUsers']);
        Route::get('user-status/{userId}', [UserStatusController::class, 'getUserStatus']);
        Route::post('heartbeat', [UserStatusController::class, 'heartbeat']);
    });
});