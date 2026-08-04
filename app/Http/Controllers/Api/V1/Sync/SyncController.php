<?php

namespace App\Http\Controllers\Api\V1\Sync;

use App\Actions\Sync\AckSyncAction;
use App\Actions\Sync\BuildSyncBootstrapAction;
use App\Actions\Sync\BuildSyncDeltaAction;
use App\Actions\Sync\EnsureDeviceSyncStateAction;
use App\Actions\Sync\ListMissingEnvelopesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\AckSyncRequest;
use App\Http\Resources\Sync\DeviceSyncStateResource;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SyncController extends Controller
{
    #[OA\Get(
        path: '/sync/state',
        operationId: 'syncState',
        summary: 'État de synchronisation d’un appareil',
        tags: ['Sync'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'device_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SyncState200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function state(Request $request, EnsureDeviceSyncStateAction $ensure): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $device = $this->resolveApprovedDevice($user, $request->string('device_id')->toString());

        if ($device === null) {
            return ApiResponse::error('Approved device required.', 422, [
                'device_id' => ['Device must be an approved device of the authenticated user.'],
            ]);
        }

        $state = $ensure->execute($device);

        return ApiResponse::success([
            'state' => new DeviceSyncStateResource($state),
        ]);
    }

    #[OA\Get(
        path: '/sync/bootstrap',
        operationId: 'syncBootstrap',
        summary: 'Bootstrap historique chiffré pour un appareil',
        description: 'Retourne conversations, messages, médias, enveloppes et receipts. Aucun clair.',
        tags: ['Sync'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'device_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SyncBootstrap200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function bootstrap(Request $request, BuildSyncBootstrapAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $device = $this->resolveApprovedDevice($user, $request->string('device_id')->toString());

        if ($device === null) {
            return ApiResponse::error('Approved device required.', 422, [
                'device_id' => ['Device must be an approved device of the authenticated user.'],
            ]);
        }

        return ApiResponse::success($action->execute($user, $device), 'Sync bootstrap ready.');
    }

    #[OA\Get(
        path: '/sync/delta',
        operationId: 'syncDelta',
        summary: 'Delta de synchronisation depuis un curseur',
        tags: ['Sync'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'device_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'since', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date-time')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SyncDelta200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function delta(Request $request, BuildSyncDeltaAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $device = $this->resolveApprovedDevice($user, $request->string('device_id')->toString());
        $sinceRaw = $request->string('since')->toString();

        if ($device === null || $sinceRaw === '') {
            return ApiResponse::error('device_id and since are required.', 422, [
                'device_id' => $device === null ? ['Approved device required.'] : [],
                'since' => $sinceRaw === '' ? ['A valid ISO8601 timestamp is required.'] : [],
            ]);
        }

        try {
            $since = CarbonImmutable::parse($sinceRaw);
        } catch (\Throwable) {
            return ApiResponse::error('Invalid since timestamp.', 422, [
                'since' => ['Must be a valid ISO8601 datetime.'],
            ]);
        }

        return ApiResponse::success($action->execute($user, $device, $since), 'Sync delta ready.');
    }

    #[OA\Post(
        path: '/sync/ack',
        operationId: 'syncAck',
        summary: 'Accuser réception d’une sync (avancer le curseur)',
        tags: ['Sync'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['device_id', 'cursor_at'],
                example: [
                    'device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'cursor_at' => '2026-08-03T21:00:00.000000Z',
                    'bootstrap_completed' => true,
                    'last_message_id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SyncAck200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function ack(AckSyncRequest $request, AckSyncAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $state = $action->execute($user, $request->validated());

        return ApiResponse::success([
            'state' => new DeviceSyncStateResource($state),
        ], 'Sync cursor updated.');
    }

    #[OA\Get(
        path: '/sync/devices/{id}/missing-envelopes',
        operationId: 'syncMissingEnvelopes',
        summary: 'Contenus historiques sans enveloppe pour un appareil cible',
        description: 'Utilisé par un appareil approuvé pour re-wraper les CEK vers le nouvel appareil.',
        tags: ['Sync'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'actor_device_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SyncMissingEnvelopes200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function missingEnvelopes(Request $request, string $id, ListMissingEnvelopesAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $actor = $this->resolveApprovedDevice($user, $request->string('actor_device_id')->toString());
        $target = $this->resolveApprovedDevice($user, $id);

        if ($actor === null || $target === null) {
            return ApiResponse::error('Approved actor and target devices required.', 422, [
                'actor_device_id' => $actor === null ? ['Approved actor device required.'] : [],
                'id' => $target === null ? ['Approved target device required.'] : [],
            ]);
        }

        return ApiResponse::success(
            $action->execute($user, $actor, $target),
            'Missing envelopes listed.',
        );
    }

    private function resolveApprovedDevice(User $user, string $deviceId): ?UserDevice
    {
        if ($deviceId === '') {
            return null;
        }

        $device = UserDevice::query()->find($deviceId);

        if ($device === null || $device->user_id !== $user->id || ! $device->isApproved()) {
            return null;
        }

        return $device;
    }
}
