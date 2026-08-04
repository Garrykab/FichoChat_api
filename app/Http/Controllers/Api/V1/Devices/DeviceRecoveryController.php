<?php

namespace App\Http\Controllers\Api\V1\Devices;

use App\Actions\Devices\ConfirmAccountRecoveryAction;
use App\Actions\Devices\DeleteRecoveryBackupAction;
use App\Actions\Devices\RequestDeviceRecoveryOtpAction;
use App\Actions\Devices\RestoreWithRecoveryPhraseAction;
use App\Actions\Devices\UpsertRecoveryBackupAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Devices\ConfirmAccountRecoveryRequest;
use App\Http\Requests\Devices\RestoreWithRecoveryPhraseRequest;
use App\Http\Requests\Devices\UpsertRecoveryBackupRequest;
use App\Http\Resources\Devices\DeviceResource;
use App\Http\Resources\Devices\RecoveryBackupResource;
use App\Models\DeviceRecoveryBackup;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceRecoveryController extends Controller
{
    public function requestOtp(Request $request, RequestDeviceRecoveryOtpAction $action): JsonResponse
    {
        $action->execute($request->user(), $request);

        return ApiResponse::success([], 'Recovery code sent to your email.');
    }

    public function confirmAccount(
        ConfirmAccountRecoveryRequest $request,
        ConfirmAccountRecoveryAction $action,
    ): JsonResponse {
        $device = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success([
            'device' => new DeviceResource($device),
            'history_preserved' => false,
        ], 'Account recovered. Previous devices were revoked. Encrypted history is not available on this device.');
    }

    public function restoreWithPhrase(
        RestoreWithRecoveryPhraseRequest $request,
        RestoreWithRecoveryPhraseAction $action,
    ): JsonResponse {
        $device = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success([
            'device' => new DeviceResource($device),
            'history_preserved' => true,
        ], 'Device restored from recovery phrase.');
    }

    public function showBackup(Request $request): JsonResponse
    {
        $backup = DeviceRecoveryBackup::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($backup === null) {
            return ApiResponse::success([
                'has_backup' => false,
                'backup' => null,
            ]);
        }

        return ApiResponse::success([
            'has_backup' => true,
            'backup' => new RecoveryBackupResource($backup),
        ]);
    }

    public function upsertBackup(
        UpsertRecoveryBackupRequest $request,
        UpsertRecoveryBackupAction $action,
    ): JsonResponse {
        $backup = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success([
            'has_backup' => true,
            'backup' => new RecoveryBackupResource($backup),
        ], 'Recovery backup saved.');
    }

    public function deleteBackup(Request $request, DeleteRecoveryBackupAction $action): JsonResponse
    {
        $deleted = $action->execute($request->user(), $request);

        return ApiResponse::success([
            'has_backup' => false,
            'deleted' => $deleted,
        ], $deleted ? 'Recovery backup deleted.' : 'No recovery backup to delete.');
    }
}
