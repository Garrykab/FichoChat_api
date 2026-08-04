<?php

namespace App\Http\Controllers\Api\V1\Presence;

use App\Actions\Presence\TouchUserLastSeenAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PresenceController extends Controller
{
    #[OA\Post(
        path: '/presence/heartbeat',
        operationId: 'presenceHeartbeat',
        summary: 'Signaler sa présence (last_seen)',
        description: 'No-op si show_last_seen=false.',
        tags: ['Presence'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Email non vérifié'),
        ],
    )]
    public function heartbeat(Request $request, TouchUserLastSeenAction $touch): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $touch->execute($user->loadMissing('settings'));

        return ApiResponse::success([
            'shared' => $updated !== null,
            'last_seen_at' => $updated?->last_seen_at?->toIso8601String(),
        ]);
    }
}
