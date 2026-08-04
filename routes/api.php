<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Conversations\ConversationController;
use App\Http\Controllers\Api\V1\Devices\DeviceController;
use App\Http\Controllers\Api\V1\Devices\DeviceRecoveryController;
use App\Http\Controllers\Api\V1\Keys\KeyController;
use App\Http\Controllers\Api\V1\Media\MediaController;
use App\Http\Controllers\Api\V1\Media\MediaLimitsController;
use App\Http\Controllers\Api\V1\Messages\MessageController;
use App\Http\Controllers\Api\V1\Presence\PresenceController;
use App\Http\Controllers\Api\V1\Realtime\BroadcastAuthController;
use App\Http\Controllers\Api\V1\Realtime\RealtimeConfigController;
use App\Http\Controllers\Api\V1\Security\SecurityController;
use App\Http\Controllers\Api\V1\Settings\SettingsController;
use App\Http\Controllers\Api\V1\Sync\SyncController;
use App\Http\Controllers\Api\V1\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh'])
        ->middleware(['throttle:30,1', 'auth.origin']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('email/verify', [AuthController::class, 'verifyEmail']);
    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware(['throttle:30,1', 'auth.origin']);

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('email/resend', [AuthController::class, 'resendVerificationEmail']);
    });
});

Route::middleware(['auth:api', 'verified.email'])->prefix('users')->group(function (): void {
    Route::get('me', [UserController::class, 'me']);
    Route::post('me/profile', [UserController::class, 'updateProfile']);
    Route::post('me/setup-profile', [UserController::class, 'setupProfile']);
    Route::post('me/password', [UserController::class, 'changePassword']);
    Route::get('search', [UserController::class, 'search']);
    Route::get('{id}/avatar', [UserController::class, 'downloadAvatar'])->whereUuid('id');
    Route::get('{id}', [UserController::class, 'show'])->whereUuid('id');
});

Route::middleware(['auth:api', 'verified.email'])->prefix('devices')->group(function (): void {
    Route::get('/', [DeviceController::class, 'index']);
    Route::post('/', [DeviceController::class, 'store']);
    Route::get('pending', [DeviceController::class, 'pending']);

    Route::post('recovery/request-otp', [DeviceRecoveryController::class, 'requestOtp'])
        ->middleware('throttle:5,10');
    Route::post('recovery/confirm', [DeviceRecoveryController::class, 'confirmAccount'])
        ->middleware('throttle:10,10');
    Route::post('recovery/restore', [DeviceRecoveryController::class, 'restoreWithPhrase'])
        ->middleware('throttle:10,10');
    Route::get('recovery/backup', [DeviceRecoveryController::class, 'showBackup']);
    Route::put('recovery/backup', [DeviceRecoveryController::class, 'upsertBackup']);
    Route::delete('recovery/backup', [DeviceRecoveryController::class, 'deleteBackup']);

    Route::get('{id}', [DeviceController::class, 'show'])->whereUuid('id');
    Route::post('{id}/approve', [DeviceController::class, 'approve'])->whereUuid('id');
    Route::post('{id}/revoke', [DeviceController::class, 'revoke'])->whereUuid('id');
    Route::post('{id}/replace-key', [DeviceController::class, 'replaceKey'])->whereUuid('id');
});

Route::middleware(['auth:api', 'verified.email'])->prefix('keys')->group(function (): void {
    Route::get('me/public', [KeyController::class, 'myPublicKeys']);
    Route::get('users/{userId}/public', [KeyController::class, 'userPublicKeys'])->whereUuid('userId');
    Route::post('envelopes', [KeyController::class, 'storeEnvelopes']);
    Route::get('envelopes', [KeyController::class, 'listEnvelopes']);
    Route::get('envelopes/for-me', [KeyController::class, 'envelopesForMe']);
    Route::post('devices/{id}/rotate', [KeyController::class, 'rotate'])->whereUuid('id');
});

Route::middleware(['auth:api', 'verified.email'])->prefix('conversations')->group(function (): void {
    Route::get('/', [ConversationController::class, 'index']);
    Route::post('/', [ConversationController::class, 'store']);
    Route::get('{id}', [ConversationController::class, 'show'])->whereUuid('id');
    Route::post('{id}/archive', [ConversationController::class, 'archive'])->whereUuid('id');
    Route::post('{id}/unarchive', [ConversationController::class, 'unarchive'])->whereUuid('id');
    Route::post('{id}/hide', [ConversationController::class, 'hide'])->whereUuid('id');
    Route::post('{id}/typing', [ConversationController::class, 'typing'])->whereUuid('id');
    Route::get('{conversationId}/messages', [MessageController::class, 'index'])->whereUuid('conversationId');
    Route::post('{conversationId}/messages', [MessageController::class, 'store'])->whereUuid('conversationId');
});

Route::middleware(['auth:api', 'verified.email'])->prefix('messages')->group(function (): void {
    Route::get('{id}', [MessageController::class, 'show'])->whereUuid('id');
    Route::patch('{id}', [MessageController::class, 'update'])->whereUuid('id');
    Route::delete('{id}', [MessageController::class, 'destroy'])->whereUuid('id');
    Route::delete('{id}/for-me', [MessageController::class, 'destroyForMe'])->whereUuid('id');
    Route::post('{id}/receipts', [MessageController::class, 'receipt'])->whereUuid('id');
});

Route::middleware(['auth:api', 'verified.email'])->prefix('media')->group(function (): void {
    Route::get('limits', MediaLimitsController::class);
    Route::post('sessions', [MediaController::class, 'createSession']);
    Route::put('sessions/{id}/content', [MediaController::class, 'uploadContent'])->whereUuid('id');
    Route::post('sessions/{id}/content', [MediaController::class, 'uploadContent'])->whereUuid('id');
    Route::post('sessions/{id}/complete', [MediaController::class, 'complete'])->whereUuid('id');
    Route::get('{id}', [MediaController::class, 'show'])->whereUuid('id');
    Route::get('{id}/download', [MediaController::class, 'download'])->whereUuid('id');
});

Route::middleware(['auth:api', 'verified.email'])->prefix('sync')->group(function (): void {
    Route::get('state', [SyncController::class, 'state']);
    Route::get('bootstrap', [SyncController::class, 'bootstrap']);
    Route::get('delta', [SyncController::class, 'delta']);
    Route::post('ack', [SyncController::class, 'ack']);
    Route::get('devices/{id}/missing-envelopes', [SyncController::class, 'missingEnvelopes'])->whereUuid('id');
});

Route::middleware(['auth:api', 'verified.email'])->prefix('presence')->group(function (): void {
    Route::post('heartbeat', [PresenceController::class, 'heartbeat']);
});

Route::middleware(['auth:api'])->post('broadcasting/auth', [BroadcastAuthController::class, 'authenticate']);

Route::middleware(['auth:api', 'verified.email'])->get('realtime/config', RealtimeConfigController::class);

Route::middleware(['auth:api', 'verified.email'])->prefix('security')->group(function (): void {
    Route::get('events', [SecurityController::class, 'events']);
});

Route::middleware(['auth:api', 'verified.email'])->prefix('activity')->group(function (): void {
    Route::get('logs', [SecurityController::class, 'activity']);
});

Route::middleware(['auth:api', 'verified.email'])->prefix('settings')->group(function (): void {
    Route::get('/', [SettingsController::class, 'show']);
    Route::patch('/', [SettingsController::class, 'update']);
    Route::put('/', [SettingsController::class, 'update']);
});

Route::middleware(['auth:api', 'verified.email', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('stats', [AdminController::class, 'stats']);
    Route::get('users', [AdminController::class, 'users']);
    Route::get('users/{id}', [AdminController::class, 'showUser'])->whereUuid('id');
    Route::post('users/{id}/suspend', [AdminController::class, 'suspendUser'])->whereUuid('id');
    Route::post('users/{id}/activate', [AdminController::class, 'activateUser'])->whereUuid('id');
    Route::get('devices', [AdminController::class, 'devices']);
    Route::post('devices/{id}/revoke', [AdminController::class, 'revokeDevice'])->whereUuid('id');
    Route::get('security/events', [AdminController::class, 'securityEvents']);
    Route::get('activity/logs', [AdminController::class, 'activityLogs']);
    Route::get('settings', [AdminSettingsController::class, 'show'])
        ->middleware('permission:admin.settings.view');
    Route::patch('settings', [AdminSettingsController::class, 'update'])
        ->middleware('permission:admin.settings.update');
    Route::put('settings', [AdminSettingsController::class, 'update'])
        ->middleware('permission:admin.settings.update');
});
