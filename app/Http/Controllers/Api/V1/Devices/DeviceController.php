<?php

namespace App\Http\Controllers\Api\V1\Devices;

use App\Actions\Devices\ApproveDeviceAction;
use App\Actions\Devices\RegisterDeviceAction;
use App\Actions\Devices\ReplaceDeviceKeyAction;
use App\Actions\Devices\RevokeDeviceAction;
use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Devices\DeviceActorRequest;
use App\Http\Requests\Devices\RegisterDeviceRequest;
use App\Http\Requests\Devices\ReplaceDeviceKeyRequest;
use App\Http\Resources\Devices\DeviceResource;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DeviceController extends Controller
{
    #[OA\Get(
        path: '/devices',
        operationId: 'devicesIndex',
        summary: 'Lister les appareils',
        security: [['bearerAuth' => []]],
        tags: ['Devices'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des appareils', content: new OA\JsonContent(ref: '#/components/schemas/DevicesIndex200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $devices = $user->devices()->latest()->get();

        return ApiResponse::success([
            'devices' => DeviceResource::collection($devices),
        ]);
    }

    #[OA\Post(
        path: '/devices',
        operationId: 'devicesRegister',
        summary: 'Enregistrer un appareil',
        description: 'Le client envoie uniquement la clé publique. Le premier appareil est auto-approuvé ; les suivants restent pending.',
        security: [['bearerAuth' => []]],
        tags: ['Devices'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'platform', 'public_key'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'MacBook Pro'),
                    new OA\Property(property: 'platform', type: 'string', enum: ['web', 'ios', 'android', 'desktop'], example: 'web'),
                    new OA\Property(property: 'public_key', type: 'string', example: '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----'),
                ],
                example: [
                    'name' => 'MacBook Pro',
                    'platform' => 'web',
                    'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAExamplePublicKeyDataHere==\n-----END PUBLIC KEY-----',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Appareil enregistré', content: new OA\JsonContent(ref: '#/components/schemas/DevicesRegister201')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function store(RegisterDeviceRequest $request, RegisterDeviceAction $action): JsonResponse
    {
        $device = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::created([
            'device' => new DeviceResource($device),
        ], 'Device registered successfully.');
    }

    #[OA\Get(
        path: '/devices/{id}',
        operationId: 'devicesShow',
        summary: 'Détail d’un appareil',
        security: [['bearerAuth' => []]],
        tags: ['Devices'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Appareil', content: new OA\JsonContent(ref: '#/components/schemas/DevicesShow200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $device = $this->findOwnedDevice($request->user(), $id);

        if ($device === null) {
            return ApiResponse::error('Device not found.', 404);
        }

        return ApiResponse::success([
            'device' => new DeviceResource($device),
        ]);
    }

    #[OA\Post(
        path: '/devices/{id}/approve',
        operationId: 'devicesApprove',
        summary: 'Approuver un appareil',
        description: 'Doit être appelé depuis un appareil déjà approuvé (actor_device_id).',
        security: [['bearerAuth' => []]],
        tags: ['Devices'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['actor_device_id'],
                properties: [
                    new OA\Property(property: 'actor_device_id', type: 'string', format: 'uuid'),
                ],
                example: [
                    'actor_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Appareil approuvé', content: new OA\JsonContent(ref: '#/components/schemas/DevicesApprove200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation / règles métier', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function approve(
        DeviceActorRequest $request,
        string $id,
        ApproveDeviceAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $target = $this->findOwnedDevice($user, $id);
        $actor = $this->findOwnedDevice($user, $request->validated('actor_device_id'));

        if ($target === null || $actor === null) {
            return ApiResponse::error('Device not found.', 404);
        }

        $device = $action->execute($actor, $target);

        return ApiResponse::success([
            'device' => new DeviceResource($device),
        ], 'Device approved successfully.');
    }

    #[OA\Post(
        path: '/devices/{id}/revoke',
        operationId: 'devicesRevoke',
        summary: 'Révoquer un appareil',
        description: 'Révoque l’appareil et invalide ses refresh tokens.',
        security: [['bearerAuth' => []]],
        tags: ['Devices'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['actor_device_id'],
                properties: [
                    new OA\Property(property: 'actor_device_id', type: 'string', format: 'uuid'),
                ],
                example: [
                    'actor_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Appareil révoqué', content: new OA\JsonContent(ref: '#/components/schemas/DevicesRevoke200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation / règles métier', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function revoke(
        DeviceActorRequest $request,
        string $id,
        RevokeDeviceAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $target = $this->findOwnedDevice($user, $id);
        $actor = $this->findOwnedDevice($user, $request->validated('actor_device_id'));

        if ($target === null || $actor === null) {
            return ApiResponse::error('Device not found.', 404);
        }

        $device = $action->execute($actor, $target);

        return ApiResponse::success([
            'device' => new DeviceResource($device),
        ], 'Device revoked successfully.');
    }

    #[OA\Post(
        path: '/devices/{id}/replace-key',
        operationId: 'devicesReplaceKey',
        summary: 'Remplacer la clé publique d’un appareil',
        description: 'Un appareil approved peut régénérer sa paire de clés (même id) pour créer une phrase de récupération.',
        security: [['bearerAuth' => []]],
        tags: ['Devices'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['actor_device_id', 'public_key'],
                properties: [
                    new OA\Property(property: 'actor_device_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'public_key', type: 'string'),
                ],
                example: [
                    'actor_device_id' => '019fc7e1-1111-2222-3333-444455556666',
                    'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Clé remplacée', content: new OA\JsonContent(ref: '#/components/schemas/DevicesReplaceKey200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation / règles métier', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function replaceKey(
        ReplaceDeviceKeyRequest $request,
        string $id,
        ReplaceDeviceKeyAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $target = $this->findOwnedDevice($user, $id);
        $actorId = $request->validated('actor_device_id');

        if ($target === null) {
            return ApiResponse::error('Device not found.', 404);
        }

        if ($actorId !== $target->id) {
            return ApiResponse::error('Only the device itself can replace its key.', 422);
        }

        $device = $action->execute($target, [
            'public_key' => $request->validated('public_key'),
        ], $request);

        return ApiResponse::success([
            'device' => new DeviceResource($device),
        ], 'Device key replaced successfully.');
    }

    #[OA\Get(
        path: '/devices/pending',
        operationId: 'devicesPending',
        summary: 'Appareils en attente',
        security: [['bearerAuth' => []]],
        tags: ['Devices'],
        responses: [
            new OA\Response(response: 200, description: 'Appareils pending', content: new OA\JsonContent(ref: '#/components/schemas/DevicesPending200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function pending(Request $request): JsonResponse
    {
        $devices = $request->user()
            ->devices()
            ->where('status', DeviceStatus::Pending)
            ->latest()
            ->get();

        return ApiResponse::success([
            'devices' => DeviceResource::collection($devices),
        ]);
    }

    private function findOwnedDevice(User $user, string $id): ?UserDevice
    {
        return UserDevice::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }
}
