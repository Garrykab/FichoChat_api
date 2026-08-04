<?php

namespace App\Http\Controllers\Api\V1\Messages;

use App\Actions\Messages\DeleteMessageAction;
use App\Actions\Messages\SendMessageAction;
use App\Actions\Messages\UpdateMessageAction;
use App\Actions\Messages\UpdateReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\SendMessageRequest;
use App\Http\Requests\Messages\UpdateMessageRequest;
use App\Http\Requests\Messages\UpdateReceiptRequest;
use App\Http\Resources\Messages\MessageReceiptResource;
use App\Http\Resources\Messages\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MessageController extends Controller
{
    #[OA\Get(
        path: '/conversations/{conversationId}/messages',
        operationId: 'messagesIndex',
        summary: 'Lister les messages d’une conversation',
        description: 'Retourne ciphertext + iv (jamais de clair). Pagination cursor via `before` (UUID) + `limit`.',
        tags: ['Messages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'conversationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'before', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MessagesIndex200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function index(Request $request, string $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->findParticipatingConversation($user, $conversationId);

        if ($conversation === null) {
            return ApiResponse::error('Conversation not found.', 404);
        }

        $limit = min(100, max(1, (int) $request->integer('limit', 50)));
        $before = $request->string('before')->toString() ?: null;

        $query = Message::withTrashed()
            ->where('conversation_id', $conversation->id)
            ->whereDoesntHave('userDeletes', fn ($q) => $q->where('user_id', $user->id))
            ->with(['receipts', 'sender.profile', 'medias'])
            ->orderByDesc('created_at');

        if ($before) {
            $cursor = Message::withTrashed()->find($before);
            if ($cursor !== null) {
                $query->where('created_at', '<', $cursor->created_at);
            }
        }

        $messages = $query->limit($limit)->get()->sortBy('created_at')->values();

        return ApiResponse::success([
            'messages' => MessageResource::collection($messages),
        ]);
    }

    #[OA\Post(
        path: '/conversations/{conversationId}/messages',
        operationId: 'messagesStore',
        summary: 'Envoyer un message chiffré',
        description: 'Le body contient ciphertext + iv + enveloppes CEK. Aucun texte en clair.',
        tags: ['Messages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'conversationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['sender_device_id', 'ciphertext', 'iv', 'envelopes'],
                example: [
                    'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'ciphertext' => 'base64…',
                    'iv' => 'base64…',
                    'type' => 'text',
                    'envelopes' => [
                        [
                            'recipient_device_id' => '019fc7e1-1111-2222-3333-444455556666',
                            'encrypted_cek' => 'base64…',
                            'ephemeral_public_key' => '-----BEGIN PUBLIC KEY-----\n…',
                            'iv' => 'base64…',
                        ],
                    ],
                    'media_ids' => [],
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Créé', content: new OA\JsonContent(ref: '#/components/schemas/MessagesStore201')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function store(
        SendMessageRequest $request,
        string $conversationId,
        SendMessageAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->findParticipatingConversation($user, $conversationId);

        if ($conversation === null) {
            return ApiResponse::error('Conversation not found.', 404);
        }

        $message = $action->execute($user, $conversation, $request->validated());

        return ApiResponse::created([
            'message' => new MessageResource($message),
        ], 'Message sent successfully.');
    }

    #[OA\Get(
        path: '/messages/{id}',
        operationId: 'messagesShow',
        summary: 'Détail d’un message',
        tags: ['Messages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MessagesShow200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $message = $this->findAccessibleMessage($user, $id);

        if ($message === null) {
            return ApiResponse::error('Message not found.', 404);
        }

        if ($message->isDeletedForUser($user->id)) {
            return ApiResponse::error('Message not found.', 404);
        }

        $message->load(['receipts', 'sender.profile', 'medias']);

        return ApiResponse::success([
            'message' => new MessageResource($message),
        ]);
    }

    #[OA\Patch(
        path: '/messages/{id}',
        operationId: 'messagesUpdate',
        summary: 'Modifier un message (nouveau ciphertext)',
        tags: ['Messages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MessagesShow200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function update(
        UpdateMessageRequest $request,
        string $id,
        UpdateMessageAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $message = $this->findAccessibleMessage($user, $id, withTrashed: false);

        if ($message === null) {
            return ApiResponse::error('Message not found.', 404);
        }

        $updated = $action->execute($user, $message, $request->validated());

        return ApiResponse::success([
            'message' => new MessageResource($updated),
        ], 'Message updated successfully.');
    }

    #[OA\Delete(
        path: '/messages/{id}',
        operationId: 'messagesDestroy',
        summary: 'Supprimer un message pour tous',
        description: 'Réservé à l’expéditeur. Pour une suppression locale : DELETE /messages/{id}/for-me.',
        tags: ['Messages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MessagesShow200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function destroy(Request $request, string $id, DeleteMessageAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $message = $this->findAccessibleMessage($user, $id, withTrashed: false);

        if ($message === null) {
            return ApiResponse::error('Message not found.', 404);
        }

        $deleted = $action->execute($user, $message, 'everyone');

        return ApiResponse::success([
            'message' => new MessageResource($deleted),
        ], 'Message deleted successfully.');
    }

    #[OA\Delete(
        path: '/messages/{id}/for-me',
        operationId: 'messagesDestroyForMe',
        summary: 'Supprimer un message pour soi uniquement',
        description: 'Le message disparaît de la liste pour l’utilisateur courant ; l’autre participant le conserve.',
        tags: ['Messages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MessagesDeleteForMe200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function destroyForMe(Request $request, string $id, DeleteMessageAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $message = $this->findAccessibleMessage($user, $id, withTrashed: false);

        if ($message === null || $message->isDeletedForUser($user->id)) {
            return ApiResponse::error('Message not found.', 404);
        }

        $action->execute($user, $message, 'me');

        return ApiResponse::success([
            'message_id' => $message->id,
            'deleted_for_me' => true,
        ], 'Message deleted for you.');
    }

    #[OA\Post(
        path: '/messages/{id}/receipts',
        operationId: 'messagesReceipt',
        summary: 'Accusé delivered / read',
        tags: ['Messages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                example: ['status' => 'read', 'device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff'],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MessagesReceipt200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function receipt(
        UpdateReceiptRequest $request,
        string $id,
        UpdateReceiptAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $message = $this->findAccessibleMessage($user, $id, withTrashed: false);

        if ($message === null) {
            return ApiResponse::error('Message not found.', 404);
        }

        $receipt = $action->execute($user, $message, $request->validated());

        return ApiResponse::success([
            'receipt' => new MessageReceiptResource($receipt),
        ], 'Receipt updated successfully.');
    }

    private function findParticipatingConversation(User $user, string $id): ?Conversation
    {
        return Conversation::query()
            ->whereKey($id)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->first();
    }

    private function findAccessibleMessage(User $user, string $id, bool $withTrashed = true): ?Message
    {
        $query = $withTrashed ? Message::withTrashed() : Message::query();

        $message = $query->find($id);

        if ($message === null) {
            return null;
        }

        $allowed = $message->conversation()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        return $allowed ? $message : null;
    }
}
