<?php

namespace App\Http\Controllers\Api\V1\Conversations;

use App\Actions\Conversations\CreatePrivateConversationAction;
use App\Actions\Conversations\UpdateParticipantStatusAction;
use App\Enums\ParticipantStatus;
use App\Events\ConversationTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\CreateConversationRequest;
use App\Http\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ConversationController extends Controller
{
    #[OA\Get(
        path: '/conversations',
        operationId: 'conversationsIndex',
        summary: 'Lister mes conversations',
        description: 'Par défaut : conversations actives (non masquées). `?status=archived` pour les archivées. `?status=all` pour tout sauf hidden.',
        tags: ['Conversations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'archived', 'all'], default: 'active')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationsIndex200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = $request->string('status')->toString() ?: 'active';

        $query = Conversation::query()
            ->whereHas('participants', function ($q) use ($user, $status): void {
                $q->where('user_id', $user->id);

                if ($status === 'archived') {
                    $q->where('status', ParticipantStatus::Archived);
                } elseif ($status === 'all') {
                    $q->whereIn('status', [ParticipantStatus::Active, ParticipantStatus::Archived]);
                } else {
                    $q->where('status', ParticipantStatus::Active);
                }
            })
            ->with(['participants.user.profile', 'participants.user.settings'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        return ApiResponse::success([
            'conversations' => ConversationResource::collection($query->get()),
        ]);
    }

    #[OA\Post(
        path: '/conversations',
        operationId: 'conversationsStore',
        summary: 'Créer / reprendre une conversation privée',
        description: 'V1 : 1-1 uniquement. Si une conversation existe déjà entre les deux users, elle est renvoyée (et réactivée pour l’appelant).',
        tags: ['Conversations'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
                ],
                example: ['user_id' => '019fc7e2-1111-2222-3333-444455556666'],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Créée / reprise', content: new OA\JsonContent(ref: '#/components/schemas/ConversationsStore201')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function store(CreateConversationRequest $request, CreatePrivateConversationAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $action->execute($user, $request->validated());
        $wasRecentlyCreated = $conversation->wasRecentlyCreated;

        return ApiResponse::success(
            ['conversation' => new ConversationResource($conversation)],
            $wasRecentlyCreated ? 'Conversation created successfully.' : 'Conversation resumed successfully.',
            $wasRecentlyCreated ? 201 : 200,
        );
    }

    #[OA\Get(
        path: '/conversations/{id}',
        operationId: 'conversationsShow',
        summary: 'Détail d’une conversation',
        tags: ['Conversations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationsShow200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié / non participant', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->findParticipating($user, $id);

        if ($conversation === null) {
            return ApiResponse::error('Conversation not found.', 404);
        }

        $conversation->load(['participants.user.profile', 'participants.user.settings']);

        return ApiResponse::success([
            'conversation' => new ConversationResource($conversation),
        ]);
    }

    #[OA\Post(
        path: '/conversations/{id}/archive',
        operationId: 'conversationsArchive',
        summary: 'Archiver une conversation (pour moi)',
        tags: ['Conversations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationsStatus200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function archive(Request $request, string $id, UpdateParticipantStatusAction $action): JsonResponse
    {
        return $this->mutateStatus($request, $id, fn (User $user, Conversation $c) => $action->archive($user, $c));
    }

    #[OA\Post(
        path: '/conversations/{id}/unarchive',
        operationId: 'conversationsUnarchive',
        summary: 'Désarchiver une conversation (pour moi)',
        tags: ['Conversations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationsStatus200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function unarchive(Request $request, string $id, UpdateParticipantStatusAction $action): JsonResponse
    {
        return $this->mutateStatus($request, $id, fn (User $user, Conversation $c) => $action->unarchive($user, $c));
    }

    #[OA\Post(
        path: '/conversations/{id}/hide',
        operationId: 'conversationsHide',
        summary: 'Supprimer une conversation pour soi',
        description: 'Masque la conversation pour l’utilisateur courant uniquement. Ne supprime pas pour l’autre participant. Un nouveau message ou POST /conversations la réactive.',
        tags: ['Conversations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationsStatus200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function hide(Request $request, string $id, UpdateParticipantStatusAction $action): JsonResponse
    {
        return $this->mutateStatus($request, $id, fn (User $user, Conversation $c) => $action->hide($user, $c));
    }

    #[OA\Post(
        path: '/conversations/{id}/typing',
        operationId: 'conversationsTyping',
        summary: 'Signaler qu’un utilisateur est en train d’écrire',
        tags: ['Conversations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['typing'],
                example: ['typing' => true],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function typing(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->findParticipating($user, $id);

        if ($conversation === null) {
            return ApiResponse::error('Conversation not found.', 404);
        }

        $validated = $request->validate([
            'typing' => ['required', 'boolean'],
        ]);

        ConversationTyping::dispatch($conversation->id, $user->id, (bool) $validated['typing']);

        return ApiResponse::success([
            'conversation_id' => $conversation->id,
            'typing' => (bool) $validated['typing'],
        ]);
    }

    /**
     * @param  callable(User, Conversation): mixed  $callback
     */
    private function mutateStatus(Request $request, string $id, callable $callback): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->findParticipating($user, $id, includeHidden: true);

        if ($conversation === null) {
            return ApiResponse::error('Conversation not found.', 404);
        }

        $callback($user, $conversation);
        $conversation->load(['participants.user.profile', 'participants.user.settings']);

        return ApiResponse::success([
            'conversation' => new ConversationResource($conversation->fresh()->load(['participants.user.profile', 'participants.user.settings'])),
        ], 'Conversation updated successfully.');
    }

    private function findParticipating(User $user, string $id, bool $includeHidden = false): ?Conversation
    {
        $conversation = Conversation::query()->find($id);

        if ($conversation === null) {
            return null;
        }

        $participant = $conversation->participants()
            ->where('user_id', $user->id)
            ->first();

        if ($participant === null) {
            return null;
        }

        if (! $includeHidden && $participant->status === ParticipantStatus::Hidden) {
            return null;
        }

        return $conversation;
    }
}
