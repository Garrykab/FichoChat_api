<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\ActivateUserAction;
use App\Actions\Admin\AdminRevokeDeviceAction;
use App\Actions\Admin\BuildAdminStatsAction;
use App\Actions\Admin\SuspendUserAction;
use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminDeviceResource;
use App\Http\Resources\Admin\AdminUserResource;
use App\Http\Resources\Security\ActivityLogResource;
use App\Http\Resources\Security\SecurityEventResource;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\Models\Activity;

class AdminController extends Controller
{
    #[OA\Get(
        path: '/admin/stats',
        operationId: 'adminStats',
        summary: 'Tableau de bord admin (compteurs uniquement)',
        description: 'Aucun contenu de message / média / clé. Compteurs agrégés pour supervision.',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AdminStats200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
        ],
    )]
    public function stats(BuildAdminStatsAction $action): JsonResponse
    {
        return ApiResponse::success($action->execute(), 'Admin stats ready.');
    }

    #[OA\Get(
        path: '/admin/users',
        operationId: 'adminUsersIndex',
        summary: 'Lister les utilisateurs',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AdminUsers200')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
        ],
    )]
    public function users(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $query = User::query()
            ->with('profile')
            ->withCount('devices')
            ->orderByDesc('created_at');

        if ($request->filled('q')) {
            $term = '%'.mb_strtolower($request->string('q')->toString()).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw('LOWER(username) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success([
            'users' => AdminUserResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Users listed.');
    }

    #[OA\Get(
        path: '/admin/users/{id}',
        operationId: 'adminUsersShow',
        summary: 'Détail utilisateur (métadonnées)',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AdminUserShow200')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
        ],
    )]
    public function showUser(string $id): JsonResponse
    {
        $user = User::query()->with('profile')->withCount('devices')->findOrFail($id);

        $devices = UserDevice::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success([
            'user' => new AdminUserResource($user),
            'devices' => AdminDeviceResource::collection($devices),
        ], 'User loaded.');
    }

    #[OA\Post(
        path: '/admin/users/{id}/suspend',
        operationId: 'adminUsersSuspend',
        summary: 'Suspendre un utilisateur',
        description: 'Révoque toutes les sessions. Impossible sur soi-même ou un autre admin.',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'Abuse report'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Suspendu', content: new OA\JsonContent(ref: '#/components/schemas/AdminUserAction200')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
        ],
    )]
    public function suspendUser(Request $request, string $id, SuspendUserAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $target = User::query()->findOrFail($id);
        $user = $action->execute($actor, $target, $request->string('reason')->toString() ?: null);

        return ApiResponse::success([
            'user' => new AdminUserResource($user->load('profile')->loadCount('devices')),
        ], 'User suspended.');
    }

    #[OA\Post(
        path: '/admin/users/{id}/activate',
        operationId: 'adminUsersActivate',
        summary: 'Réactiver un utilisateur',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réactivé', content: new OA\JsonContent(ref: '#/components/schemas/AdminUserAction200')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
        ],
    )]
    public function activateUser(Request $request, string $id, ActivateUserAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $target = User::query()->findOrFail($id);
        $user = $action->execute($actor, $target);

        return ApiResponse::success([
            'user' => new AdminUserResource($user->load('profile')->loadCount('devices')),
        ], 'User activated.');
    }

    #[OA\Get(
        path: '/admin/devices',
        operationId: 'adminDevicesIndex',
        summary: 'Lister les appareils (métadonnées)',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AdminDevices200')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
        ],
    )]
    public function devices(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $query = UserDevice::query()
            ->with('user')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->string('user_id')->toString());
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success([
            'devices' => AdminDeviceResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Devices listed.');
    }

    #[OA\Post(
        path: '/admin/devices/{id}/revoke',
        operationId: 'adminDevicesRevoke',
        summary: 'Révoquer un appareil (force admin)',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Révoqué', content: new OA\JsonContent(ref: '#/components/schemas/AdminDeviceRevoke200')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
        ],
    )]
    public function revokeDevice(Request $request, string $id, AdminRevokeDeviceAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $device = UserDevice::query()->findOrFail($id);
        $revoked = $action->execute($actor, $device);

        return ApiResponse::success([
            'device' => new AdminDeviceResource($revoked->load('user')),
        ], 'Device revoked by admin.');
    }

    #[OA\Get(
        path: '/admin/security/events',
        operationId: 'adminSecurityEvents',
        summary: 'Journaux de sécurité (global)',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 40)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AdminSecurityEvents200')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
        ],
    )]
    public function securityEvents(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $perPage = min(100, max(1, (int) $request->query('per_page', 40)));

        $query = SecurityEvent::query()->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->string('user_id')->toString());
        }

        $paginator = $query->paginate($perPage);

        $auditLogger->security(
            SecurityEventType::LogsViewed,
            $actor,
            ['category' => 'admin_security_events', 'count' => $paginator->total()],
            module: 'admin',
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
        path: '/admin/activity/logs',
        operationId: 'adminActivityLogs',
        summary: 'Journaux d’activité (global)',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'module', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 40)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AdminActivityLogs200')),
            new OA\Response(response: 403, description: 'Accès admin requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
        ],
    )]
    public function activityLogs(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $perPage = min(100, max(1, (int) $request->query('per_page', 40)));

        $query = Activity::query()->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('event', $request->string('type')->toString());
        }

        if ($request->filled('module')) {
            $query->where('log_name', $request->string('module')->toString());
        }

        if ($request->filled('user_id')) {
            $query->where('causer_type', User::class)
                ->where('causer_id', $request->string('user_id')->toString());
        }

        $paginator = $query->paginate($perPage);

        $auditLogger->security(
            SecurityEventType::LogsViewed,
            $actor,
            ['category' => 'admin_activity_logs', 'count' => $paginator->total()],
            module: 'admin',
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
