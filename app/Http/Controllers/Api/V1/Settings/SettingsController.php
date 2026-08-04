<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Users\EnsureUserProfileAction;
use App\Actions\Users\EnsureUserSettingsAction;
use App\Actions\Users\UpdateUserSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Resources\Settings\UserSettingResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SettingsController extends Controller
{
    #[OA\Get(
        path: '/settings',
        operationId: 'settingsShow',
        summary: 'Paramètres du compte',
        description: 'Retourne les préférences synchronisables (notifications, confidentialité) + locale/thème du profil.',
        tags: ['Settings'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SettingsShow200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function show(
        Request $request,
        EnsureUserSettingsAction $ensureSettings,
        EnsureUserProfileAction $ensureProfile,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $settings = $ensureSettings->execute($user);
        $profile = $ensureProfile->execute($user);

        return ApiResponse::success([
            'settings' => new UserSettingResource($settings),
            'appearance' => [
                'locale' => $profile->locale,
                'theme' => $profile->theme?->value ?? 'system',
            ],
        ], 'Settings loaded.');
    }

    #[OA\Patch(
        path: '/settings',
        operationId: 'settingsUpdate',
        summary: 'Mettre à jour les paramètres',
        description: 'Met à jour partiellement notifications, confidentialité, locale et thème.',
        tags: ['Settings'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'notifications_enabled', type: 'boolean', example: true),
                    new OA\Property(property: 'notify_messages', type: 'boolean', example: true),
                    new OA\Property(property: 'notify_devices', type: 'boolean', example: true),
                    new OA\Property(property: 'notify_security', type: 'boolean', example: true),
                    new OA\Property(property: 'hide_message_previews', type: 'boolean', example: false),
                    new OA\Property(property: 'silent_mode', type: 'boolean', example: false),
                    new OA\Property(property: 'send_read_receipts', type: 'boolean', example: true),
                    new OA\Property(property: 'show_last_seen', type: 'boolean', example: true),
                    new OA\Property(property: 'auto_download_media', type: 'boolean', example: false),
                    new OA\Property(property: 'locale', type: 'string', enum: ['fr', 'en'], example: 'fr'),
                    new OA\Property(property: 'theme', type: 'string', enum: ['light', 'dark', 'system'], example: 'dark'),
                ],
                example: [
                    'notifications_enabled' => true,
                    'silent_mode' => false,
                    'hide_message_previews' => true,
                    'theme' => 'dark',
                    'locale' => 'fr',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mis à jour', content: new OA\JsonContent(ref: '#/components/schemas/SettingsUpdate200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function update(UpdateSettingsRequest $request, UpdateUserSettingsAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $action->execute($user, $request->validated());

        return ApiResponse::success([
            'settings' => new UserSettingResource($result['settings']),
            'appearance' => [
                'locale' => $result['profile']->locale,
                'theme' => $result['profile']->theme?->value ?? 'system',
            ],
        ], 'Settings updated successfully.');
    }
}
