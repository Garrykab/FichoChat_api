<?php

namespace App\Http\Controllers\Api\V1\Realtime;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Broadcast;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BroadcastAuthController extends Controller
{
    #[OA\Post(
        path: '/broadcasting/auth',
        operationId: 'broadcastingAuth',
        summary: 'Authentifier un abonnement canal privé Pusher',
        tags: ['Realtime'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Autorisé'),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function authenticate(Request $request): JsonResponse|Response
    {
        /** @var User $user */
        $user = $request->user();
        $channelName = (string) $request->input('channel_name', '');
        $socketId = (string) $request->input('socket_id', '');

        if ($channelName === '' || $socketId === '') {
            return ApiResponse::error('socket_id and channel_name are required.', 422, [
                'socket_id' => $socketId === '' ? ['Required.'] : [],
                'channel_name' => $channelName === '' ? ['Required.'] : [],
            ]);
        }

        if (! $this->userCanAccessChannel($user, $channelName)) {
            return ApiResponse::error('Broadcast channel access denied.', 403);
        }

        if (filled(config('broadcasting.connections.pusher.key'))) {
            try {
                $response = Broadcast::auth($request);

                if (is_array($response)) {
                    return response()->json($response);
                }

                if ($response instanceof Response || $response instanceof JsonResponse) {
                    return $response;
                }

                return response()->json(['auth' => 'pusher-auth-unavailable']);
            } catch (AccessDeniedHttpException) {
                return ApiResponse::error('Broadcast channel access denied.', 403);
            }
        }

        // Drivers log/null (tests & local sans Pusher) : autorisation métier déjà validée
        return response()->json([
            'auth' => hash_hmac('sha256', $socketId.':'.$channelName, (string) config('app.key')),
        ]);
    }

    private function userCanAccessChannel(User $user, string $channelName): bool
    {
        if (preg_match('/^private-user\.(.+)$/', $channelName, $matches) === 1) {
            return $user->id === $matches[1];
        }

        if (preg_match('/^private-conversation\.(.+)$/', $channelName, $matches) === 1) {
            return Conversation::query()
                ->whereKey($matches[1])
                ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
                ->exists();
        }

        if (preg_match('/^presence-conversation\.(.+)\.presence$/', $channelName, $matches) === 1) {
            return Conversation::query()
                ->whereKey($matches[1])
                ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
                ->exists();
        }

        return false;
    }
}
