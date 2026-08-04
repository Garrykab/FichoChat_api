<?php

namespace App\Http\Controllers\Api\V1\Keys;

use App\Actions\Keys\RotateDeviceKeyAction;
use App\Actions\Keys\StoreKeyEnvelopesAction;
use App\Enums\DeviceStatus;
use App\Enums\EnvelopeContentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Keys\RotateDeviceKeyRequest;
use App\Http\Requests\Keys\StoreKeyEnvelopesRequest;
use App\Http\Resources\Devices\DeviceResource;
use App\Http\Resources\Keys\DevicePublicKeyResource;
use App\Http\Resources\Keys\KeyEnvelopeResource;
use App\Models\KeyEnvelope;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class KeyController extends Controller
{
    #[OA\Get(
        path: '/keys/me/public',
        operationId: 'keysMyPublic',
        summary: 'Clés publiques de mes appareils approuvés',
        tags: ['Keys'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/KeysPublicList200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function myPublicKeys(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $devices = $user->devices()
            ->where('status', DeviceStatus::Approved)
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success([
            'devices' => DevicePublicKeyResource::collection($devices),
        ]);
    }

    #[OA\Get(
        path: '/keys/users/{userId}/public',
        operationId: 'keysUserPublic',
        summary: 'Clés publiques des appareils approuvés d’un utilisateur',
        tags: ['Keys'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/KeysPublicList200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function userPublicKeys(string $userId): JsonResponse
    {
        $target = User::query()->find($userId);

        if ($target === null) {
            return ApiResponse::error('User not found.', 404);
        }

        $devices = $target->devices()
            ->where('status', DeviceStatus::Approved)
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success([
            'devices' => DevicePublicKeyResource::collection($devices),
        ]);
    }

    #[OA\Post(
        path: '/keys/envelopes',
        operationId: 'keysStoreEnvelopes',
        summary: 'Créer / remplacer des enveloppes cryptographiques',
        description: 'Stocke des CEK encapsulées (jamais de clé privée). Upsert par (content_type, content_id, recipient_device_id).',
        tags: ['Keys'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content_type', 'content_id', 'sender_device_id', 'envelopes'],
                properties: [
                    new OA\Property(property: 'content_type', type: 'string', enum: ['message', 'media', 'sync'], example: 'sync'),
                    new OA\Property(property: 'content_id', type: 'string', format: 'uuid', example: '019fc8e2-aaaa-bbbb-cccc-ddddeeeeffff'),
                    new OA\Property(property: 'sender_device_id', type: 'string', format: 'uuid'),
                    new OA\Property(
                        property: 'envelopes',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['recipient_device_id', 'encrypted_cek', 'ephemeral_public_key', 'iv'],
                            properties: [
                                new OA\Property(property: 'recipient_device_id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'encrypted_cek', type: 'string'),
                                new OA\Property(property: 'ephemeral_public_key', type: 'string'),
                                new OA\Property(property: 'iv', type: 'string'),
                                new OA\Property(property: 'algorithm', type: 'string', example: 'ECDH-P256+HKDF-SHA256+AES-256-GCM'),
                                new OA\Property(property: 'key_version', type: 'integer', example: 1),
                            ],
                        ),
                    ),
                ],
                example: [
                    'content_type' => 'sync',
                    'content_id' => '019fc8e2-aaaa-bbbb-cccc-ddddeeeeffff',
                    'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'envelopes' => [
                        [
                            'recipient_device_id' => '019fc7e1-1111-2222-3333-444455556666',
                            'encrypted_cek' => 'base64-ciphertext…',
                            'ephemeral_public_key' => '-----BEGIN PUBLIC KEY-----\n…\n-----END PUBLIC KEY-----',
                            'iv' => 'base64-iv…',
                            'algorithm' => 'ECDH-P256+HKDF-SHA256+AES-256-GCM',
                            'key_version' => 1,
                        ],
                    ],
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Créé', content: new OA\JsonContent(ref: '#/components/schemas/KeysEnvelopesStore201')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function storeEnvelopes(StoreKeyEnvelopesRequest $request, StoreKeyEnvelopesAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $envelopes = $action->execute($user, $request->validated());

        return ApiResponse::created([
            'envelopes' => KeyEnvelopeResource::collection($envelopes),
        ], 'Key envelopes stored successfully.');
    }

    #[OA\Get(
        path: '/keys/envelopes',
        operationId: 'keysListEnvelopes',
        summary: 'Lister les enveloppes d’un contenu',
        description: 'Retourne les enveloppes pour un content_id. Un destinataire ne voit que les siennes ; l’expéditeur voit celles qu’il a créées.',
        tags: ['Keys'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'content_type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['message', 'media', 'sync', 'avatar'])),
            new OA\Parameter(name: 'content_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'device_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Appareil appelant (approved)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/KeysEnvelopesList200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function listEnvelopes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_type' => ['required', 'string', Rule::enum(EnvelopeContentType::class)],
            'content_id' => ['required', 'uuid'],
            'device_id' => ['required', 'uuid'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $device = $this->ownedApprovedDevice($user, $validated['device_id']);

        if ($device === null) {
            return ApiResponse::error('Device not found or not approved.', 422, [
                'device_id' => ['Device must be an approved device of the authenticated user.'],
            ]);
        }

        $envelopes = KeyEnvelope::query()
            ->where('content_type', $validated['content_type'])
            ->where('content_id', $validated['content_id'])
            ->where(function ($query) use ($device): void {
                $query->where('recipient_device_id', $device->id)
                    ->orWhere('sender_device_id', $device->id);
            })
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success([
            'envelopes' => KeyEnvelopeResource::collection($envelopes),
        ]);
    }

    #[OA\Get(
        path: '/keys/envelopes/for-me',
        operationId: 'keysEnvelopesForMe',
        summary: 'Enveloppes destinées à mon appareil',
        tags: ['Keys'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'device_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'content_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['message', 'media', 'sync', 'avatar'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/KeysEnvelopesList200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function envelopesForMe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'uuid'],
            'content_type' => ['sometimes', 'string', Rule::enum(EnvelopeContentType::class)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $device = $this->ownedApprovedDevice($user, $validated['device_id']);

        if ($device === null) {
            return ApiResponse::error('Device not found or not approved.', 422, [
                'device_id' => ['Device must be an approved device of the authenticated user.'],
            ]);
        }

        $query = KeyEnvelope::query()
            ->where('recipient_device_id', $device->id)
            ->orderByDesc('created_at');

        if (isset($validated['content_type'])) {
            $query->where('content_type', $validated['content_type']);
        }

        return ApiResponse::success([
            'envelopes' => KeyEnvelopeResource::collection($query->limit(100)->get()),
        ]);
    }

    #[OA\Post(
        path: '/keys/devices/{id}/rotate',
        operationId: 'keysRotateDevice',
        summary: 'Rotation de la clé publique d’un appareil',
        description: 'Seul l’appareil lui-même (actor_device_id = id) peut faire tourner sa clé. Aucune clé privée n’est transmise.',
        tags: ['Keys'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['public_key', 'actor_device_id'],
                properties: [
                    new OA\Property(property: 'public_key', type: 'string'),
                    new OA\Property(property: 'actor_device_id', type: 'string', format: 'uuid'),
                ],
                example: [
                    'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                    'actor_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/KeysRotate200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Appareil introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function rotate(
        string $id,
        RotateDeviceKeyRequest $request,
        RotateDeviceKeyAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $device = UserDevice::query()->find($id);

        if ($device === null) {
            return ApiResponse::error('Device not found.', 404);
        }

        $rotated = $action->execute($user, $device, $request->validated(), $request);

        return ApiResponse::success([
            'device' => new DeviceResource($rotated),
        ], 'Device key rotated successfully.');
    }

    private function ownedApprovedDevice(User $user, string $deviceId): ?UserDevice
    {
        $device = UserDevice::query()
            ->where('id', $deviceId)
            ->where('user_id', $user->id)
            ->first();

        if ($device === null || ! $device->isApproved()) {
            return null;
        }

        return $device;
    }
}
