<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Actions\Users\ChangePasswordAction;
use App\Actions\Users\CompleteProfileSetupAction;
use App\Actions\Users\EnsureUserProfileAction;
use App\Actions\Users\SearchUsersAction;
use App\Actions\Users\UpdateProfileAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\CompleteProfileSetupRequest;
use App\Http\Requests\Users\SearchUsersRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Http\Resources\Users\PrivateUserResource;
use App\Http\Resources\Users\PublicUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    #[OA\Get(
        path: '/users/me',
        operationId: 'usersMe',
        summary: 'Profil privé courant',
        description: 'Retourne l’utilisateur authentifié avec son profil (préférences, avatar, bio).',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(response: 200, description: 'Profil privé', content: new OA\JsonContent(ref: '#/components/schemas/UsersMe200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function me(Request $request, EnsureUserProfileAction $ensureUserProfileAction): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ensureUserProfileAction->execute($user);
        $user->load('profile');

        return ApiResponse::success([
            'user' => new PrivateUserResource($user),
        ]);
    }

    #[OA\Post(
        path: '/users/me/profile',
        operationId: 'usersUpdateProfile',
        summary: 'Mettre à jour le profil',
        description: 'Met à jour display_name, bio, locale, theme et avatar. Content-Type: multipart/form-data si avatar fourni, sinon application/json.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'display_name', type: 'string', example: 'Alice Doe'),
                            new OA\Property(property: 'bio', type: 'string', example: 'Hello FichoChat'),
                            new OA\Property(property: 'locale', type: 'string', example: 'fr'),
                            new OA\Property(property: 'theme', type: 'string', enum: ['light', 'dark', 'system'], example: 'dark'),
                            new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
                        ],
                    ),
                ),
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'display_name', type: 'string', example: 'Alice Doe'),
                            new OA\Property(property: 'bio', type: 'string', example: 'Hello FichoChat'),
                            new OA\Property(property: 'locale', type: 'string', example: 'fr'),
                            new OA\Property(property: 'theme', type: 'string', enum: ['light', 'dark', 'system'], example: 'dark'),
                        ],
                        example: [
                            'display_name' => 'Alice Doe',
                            'bio' => 'Hello FichoChat',
                            'locale' => 'fr',
                            'theme' => 'dark',
                        ],
                    ),
                ),
            ],
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour', content: new OA\JsonContent(ref: '#/components/schemas/UsersUpdateProfile200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function updateProfile(
        UpdateProfileRequest $request,
        UpdateProfileAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $profile = $action->execute(
            $user,
            $request->safe()->except(['avatar']),
            $request->file('avatar'),
        );

        $user->setRelation('profile', $profile);

        return ApiResponse::success([
            'user' => new PrivateUserResource($user),
        ], 'Profile updated successfully.');
    }

    #[OA\Post(
        path: '/users/me/setup-profile',
        operationId: 'usersSetupProfile',
        summary: 'Finaliser le profil après vérification email',
        description: 'Définit un username unique et optionnellement un avatar. Obligatoire une fois après confirmation email.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        required: ['username'],
                        properties: [
                            new OA\Property(property: 'username', type: 'string', example: 'alice'),
                            new OA\Property(property: 'display_name', type: 'string', example: 'Alice'),
                            new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
                        ],
                    ),
                ),
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['username'],
                        properties: [
                            new OA\Property(property: 'username', type: 'string', example: 'alice'),
                            new OA\Property(property: 'display_name', type: 'string', example: 'Alice'),
                        ],
                        example: ['username' => 'alice', 'display_name' => 'Alice'],
                    ),
                ),
            ],
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil finalisé', content: new OA\JsonContent(ref: '#/components/schemas/UsersUpdateProfile200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function setupProfile(
        CompleteProfileSetupRequest $request,
        CompleteProfileSetupAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $user = $action->execute(
            $user,
            $request->safe()->except(['avatar']),
            $request->file('avatar'),
        );

        return ApiResponse::success([
            'user' => new PrivateUserResource($user),
        ], 'Profile setup completed.');
    }

    #[OA\Post(
        path: '/users/me/password',
        operationId: 'usersChangePassword',
        summary: 'Changer le mot de passe',
        description: 'Vérifie le mot de passe actuel. Par défaut, révoque toutes les sessions (refresh tokens).',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'Password1!'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NewPassword1!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NewPassword1!'),
                    new OA\Property(property: 'revoke_other_sessions', type: 'boolean', example: true),
                ],
                example: [
                    'current_password' => 'Password1!',
                    'password' => 'NewPassword1!',
                    'password_confirmation' => 'NewPassword1!',
                    'revoke_other_sessions' => true,
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe modifié', content: new OA\JsonContent(ref: '#/components/schemas/UsersChangePassword200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function changePassword(
        ChangePasswordRequest $request,
        ChangePasswordAction $action,
    ): JsonResponse {
        $data = $request->validated();

        $action->execute(
            $request->user(),
            $data['current_password'],
            $data['password'],
            $data['revoke_other_sessions'] ?? true,
        );

        return ApiResponse::success(null, 'Password updated successfully.');
    }

    #[OA\Get(
        path: '/users/search',
        operationId: 'usersSearch',
        summary: 'Rechercher des utilisateurs',
        description: 'Recherche les utilisateurs actifs par username ou display_name. Exclut l’utilisateur courant.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'q',
                description: 'Terme de recherche (min 2 caractères)',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 2, example: 'ali'),
                example: 'ali',
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Nombre de résultats par page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 15, example: 15),
                example: 15,
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Résultats paginés', content: new OA\JsonContent(ref: '#/components/schemas/UsersSearch200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function search(SearchUsersRequest $request, SearchUsersAction $action): JsonResponse
    {
        $paginator = $action->execute(
            $request->validated('q'),
            $request->user()->id,
            (int) $request->validated('per_page', 15),
        );

        return ApiResponse::success([
            'users' => PublicUserResource::collection($paginator->items()),
        ], 'Users retrieved successfully.', 200, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    #[OA\Get(
        path: '/users/{id}',
        operationId: 'usersShow',
        summary: 'Profil public d’un utilisateur',
        description: 'Retourne le profil public d’un utilisateur actif. L’email n’est jamais exposé.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'UUID utilisateur',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '019fc7e2-1111-2222-3333-444455556666',
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Profil public', content: new OA\JsonContent(ref: '#/components/schemas/UsersShow200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function show(string $id): JsonResponse
    {
        $user = User::query()
            ->with('profile')
            ->where('status', UserStatus::Active)
            ->find($id);

        if ($user === null) {
            return ApiResponse::error('User not found.', 404);
        }

        return ApiResponse::success([
            'user' => new PublicUserResource($user),
        ]);
    }

    #[OA\Get(
        path: '/users/{id}/avatar',
        operationId: 'usersAvatarDownload',
        summary: 'Télécharger l’avatar chiffré',
        description: 'Retourne le blob `.enc` uniquement. Le client déchiffre via enveloppe CEK (content_type=avatar, content_id=user id).',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Blob chiffré'),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
        ],
    )]
    public function downloadAvatar(string $id): StreamedResponse|JsonResponse
    {
        $user = User::query()
            ->with('profile')
            ->where('status', UserStatus::Active)
            ->find($id);

        if ($user === null || $user->profile === null || ! $user->profile->hasEncryptedAvatar()) {
            return ApiResponse::error('Avatar not found.', 404);
        }

        $profile = $user->profile;
        $disk = Storage::disk($profile->avatar_disk);

        if (! $disk->exists($profile->avatar_path)) {
            return ApiResponse::error('Avatar not found.', 404);
        }

        return response()->streamDownload(function () use ($disk, $profile): void {
            echo $disk->get($profile->avatar_path);
        }, 'avatar.enc', [
            'Content-Type' => 'application/octet-stream',
            'X-Avatar-Content-IV' => $profile->avatar_content_iv,
            'X-Avatar-Mime-Type' => $profile->avatar_mime_type ?? 'application/octet-stream',
            'X-Avatar-Checksum-SHA256' => $profile->avatar_checksum_sha256 ?? '',
        ]);
    }
}
