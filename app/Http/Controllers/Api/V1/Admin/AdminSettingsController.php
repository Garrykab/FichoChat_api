<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppSettingsRequest;
use App\Services\Settings\AppSettings;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AdminSettingsController extends Controller
{
    #[OA\Get(
        path: '/admin/settings',
        operationId: 'adminSettingsShow',
        summary: 'Lire les réglages plateforme (limites médias)',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Permission manquante'),
        ],
    )]
    public function show(AppSettings $settings): JsonResponse
    {
        return ApiResponse::success([
            'media' => $settings->mediaLimits(),
        ], 'App settings ready.');
    }

    #[OA\Patch(
        path: '/admin/settings',
        operationId: 'adminSettingsUpdate',
        summary: 'Mettre à jour les réglages plateforme (limites médias)',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Permission manquante'),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function update(UpdateAppSettingsRequest $request, AppSettings $settings): JsonResponse
    {
        $payload = $request->validated();

        if (isset($payload['media']) && is_array($payload['media'])) {
            $settings->updateMediaLimits($payload['media']);
        }

        return ApiResponse::success([
            'media' => $settings->mediaLimits(),
        ], 'App settings updated.');
    }
}
