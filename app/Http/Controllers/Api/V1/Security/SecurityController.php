<?php

namespace App\Http\Controllers\Api\V1\Security;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Security\ActivityLogResource;
use App\Http\Resources\Security\SecurityEventResource;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\Models\Activity;

class SecurityController extends Controller
{
    #[OA\Get(
        path: '/security/events',
        operationId: 'securityEventsIndex',
        summary: 'Événements de sécurité de l’utilisateur',
        description: 'Liste les événements de sécurité liés au compte authentifié (V1 : own user). La consultation est elle-même journalisée.',
        tags: ['Security'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SecurityEvents200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function events(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $query = SecurityEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        $paginator = $query->paginate($perPage);

        $auditLogger->security(
            SecurityEventType::LogsViewed,
            $user,
            ['category' => 'security_events', 'count' => $paginator->total()],
            request: $request,
        );

        return ApiResponse::success([
            'events' => SecurityEventResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Security events listed.');
    }

    #[OA\Get(
        path: '/activity/logs',
        operationId: 'activityLogsIndex',
        summary: 'Journal d’activité de l’utilisateur',
        description: 'Liste les activités métier liées au compte authentifié. La consultation est journalisée.',
        tags: ['Security'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'module', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ActivityLogs200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function activity(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $query = Activity::query()
            ->where('causer_type', User::class)
            ->where('causer_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('event', $request->string('type')->toString());
        }

        if ($request->filled('module')) {
            $query->where('log_name', $request->string('module')->toString());
        }

        $paginator = $query->paginate($perPage);

        $auditLogger->security(
            SecurityEventType::LogsViewed,
            $user,
            ['category' => 'activity_logs', 'count' => $paginator->total()],
            request: $request,
        );

        return ApiResponse::success([
            'logs' => ActivityLogResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Activity logs listed.');
    }
}
