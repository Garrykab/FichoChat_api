<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Controller;
use App\Services\Settings\AppSettings;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class MediaLimitsController extends Controller
{
    #[OA\Get(
        path: '/media/limits',
        operationId: 'mediaLimits',
        summary: 'Limites d’upload médias (lecture client)',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function __invoke(AppSettings $settings): JsonResponse
    {
        return ApiResponse::success([
            'limits' => $settings->mediaLimits(),
        ], 'Media limits ready.');
    }
}
