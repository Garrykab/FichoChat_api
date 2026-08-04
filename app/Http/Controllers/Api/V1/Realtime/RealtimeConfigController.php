<?php

namespace App\Http\Controllers\Api\V1\Realtime;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RealtimeConfigController extends Controller
{
    #[OA\Get(
        path: '/realtime/config',
        operationId: 'realtimeConfig',
        summary: 'Config publique Pusher pour le client',
        description: 'Ne renvoie jamais le secret. enabled=true si BROADCAST_CONNECTION=pusher et clé définie.',
        tags: ['Realtime'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/RealtimeConfig200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $driver = (string) (config('broadcasting.default') ?: 'null');
        $key = config('broadcasting.connections.pusher.key');
        $enabled = $driver === 'pusher' && filled($key);

        return ApiResponse::success([
            'enabled' => $enabled,
            'driver' => $driver,
            'key' => $enabled ? $key : null,
            'cluster' => $enabled ? config('broadcasting.connections.pusher.options.cluster') : null,
            'auth_endpoint' => url('/api/v1/broadcasting/auth'),
            'force_tls' => true,
        ]);
    }
}
