<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\RefreshTokenAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Devices\DeviceResource;
use App\Models\User;
use App\Services\Auth\RefreshTokenCookie;
use App\Services\Auth\TokenService;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/auth/register',
        operationId: 'authRegister',
        summary: 'Inscription',
        description: 'Crée un compte utilisateur (status pending), crée le profil, envoie un email de vérification, retourne JWT + refresh token.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'password_confirmation', 'terms_accepted'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alice@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Password1!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Password1!'),
                    new OA\Property(property: 'terms_accepted', type: 'boolean', example: true),
                ],
                example: [
                    'email' => 'alice@example.com',
                    'password' => 'Password1!',
                    'password_confirmation' => 'Password1!',
                    'terms_accepted' => true,
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé', content: new OA\JsonContent(ref: '#/components/schemas/AuthRegister201')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function register(RegisterRequest $request, RegisterUserAction $action, RefreshTokenCookie $refreshCookie): JsonResponse
    {
        $result = $action->execute(
            $request->safe()->only(['email', 'password']),
            $request,
        );

        $packaged = $refreshCookie->packageForClient($request, $result['tokens']);

        $response = ApiResponse::created([
            'user' => new UserResource($result['user']),
            'tokens' => $packaged['tokens'],
        ], 'Account created successfully. Please verify your email.');

        if ($packaged['cookie'] !== null) {
            $response->headers->setCookie($packaged['cookie']);
        }

        return $response;
    }

    #[OA\Post(
        path: '/auth/login',
        operationId: 'authLogin',
        summary: 'Connexion',
        description: 'Authentifie via email ou username. Retourne JWT + refresh token. Refuse les comptes suspended.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'password'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', example: 'alice@example.com', description: 'Email ou username'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Password1!'),
                    new OA\Property(property: 'device_id', type: 'string', format: 'uuid', nullable: true, description: 'UUID d’un appareil approuvé (Module 3)'),
                ],
                example: [
                    'login' => 'alice@example.com',
                    'password' => 'Password1!',
                    'device_id' => null,
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Connexion réussie', content: new OA\JsonContent(ref: '#/components/schemas/AuthLogin200')),
            new OA\Response(response: 422, description: 'Identifiants invalides, compte suspendu ou appareil non autorisé', content: new OA\JsonContent(ref: '#/components/schemas/AuthLoginInvalidCredentials422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function login(LoginRequest $request, LoginAction $action, RefreshTokenCookie $refreshCookie): JsonResponse
    {
        $result = $action->execute($request->validated(), $request);

        $packaged = $refreshCookie->packageForClient($request, $result['tokens']);

        $response = ApiResponse::success([
            'user' => new UserResource($result['user']),
            'tokens' => $packaged['tokens'],
            'device' => $result['device'] ? new DeviceResource($result['device']) : null,
        ], 'Logged in successfully.');

        if ($packaged['cookie'] !== null) {
            $response->headers->setCookie($packaged['cookie']);
        }

        return $response;
    }

    #[OA\Get(
        path: '/auth/me',
        operationId: 'authMe',
        summary: 'Utilisateur authentifié (auth)',
        description: 'Retourne l’utilisateur associé au JWT courant (sans profil enrichi — préférer GET /users/me).',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Profil auth', content: new OA\JsonContent(ref: '#/components/schemas/AuthMe200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function me(Request $request, \App\Actions\Admin\PromoteAdminFromConfigAction $promoteAdmin): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user = $promoteAdmin->execute($user);

        return ApiResponse::success([
            'user' => new UserResource($user),
        ]);
    }

    #[OA\Post(
        path: '/auth/refresh',
        operationId: 'authRefresh',
        summary: 'Renouveler les tokens',
        description: 'Échange un refresh token valide contre une nouvelle paire (rotation). Web : cookie HttpOnly (+ X-Client: web). Mobile : body refresh_token.',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(
                name: 'X-Client',
                in: 'header',
                required: false,
                description: 'Passer `web` pour le mode cookie HttpOnly',
                schema: new OA\Schema(type: 'string', example: 'web'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string', nullable: true, example: 'QrIrX3Y54yjPNh1c2f8TsqxiqfhqwDGKKS0ZwheVFO9W1Fh9I0TPlbg5IyumLPqo'),
                ],
                example: [
                    'refresh_token' => 'QrIrX3Y54yjPNh1c2f8TsqxiqfhqwDGKKS0ZwheVFO9W1Fh9I0TPlbg5IyumLPqo',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tokens renouvelés', content: new OA\JsonContent(ref: '#/components/schemas/AuthRefresh200')),
            new OA\Response(response: 422, description: 'Refresh token invalide', content: new OA\JsonContent(ref: '#/components/schemas/AuthRefreshInvalid422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function refresh(
        RefreshTokenRequest $request,
        RefreshTokenAction $action,
        RefreshTokenCookie $refreshCookie,
    ): JsonResponse {
        $plain = $refreshCookie->resolvePlainToken($request);

        if ($plain === null) {
            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid or expired.'],
            ]);
        }

        $tokens = $action->execute($plain, $request);
        $packaged = $refreshCookie->packageForClient($request, $tokens);

        $response = ApiResponse::success([
            'tokens' => $packaged['tokens'],
        ], 'Token refreshed successfully.');

        if ($packaged['cookie'] !== null) {
            $response->headers->setCookie($packaged['cookie']);
        }

        return $response;
    }

    #[OA\Post(
        path: '/auth/logout',
        operationId: 'authLogout',
        summary: 'Déconnexion',
        description: 'Révoque le refresh (body ou cookie HttpOnly) et invalide le JWT courant s’il est présent.',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(
                name: 'X-Client',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'web'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Déconnecté', content: new OA\JsonContent(ref: '#/components/schemas/AuthLogout200')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function logout(
        RefreshTokenRequest $request,
        TokenService $tokenService,
        RefreshTokenCookie $refreshCookie,
        \App\Services\Audit\AuditLogger $auditLogger,
    ): JsonResponse {
        $plain = $refreshCookie->resolvePlainToken($request);

        /** @var User|null $user */
        $user = $request->user();

        if ($plain !== null) {
            $token = $tokenService->findRefreshTokenByPlain($plain);
            if ($token !== null) {
                $user ??= $token->user;
                if ($token->revoked_at === null) {
                    $token->forceFill(['revoked_at' => now()])->save();
                }
            }
        }

        try {
            auth('api')->logout();
        } catch (\Throwable) {
            // Access déjà expiré / absent — OK
        }

        if ($user !== null) {
            $auditLogger->security(
                \App\Enums\SecurityEventType::Logout,
                $user,
                request: $request,
            );
        }

        $response = ApiResponse::success(null, 'Logged out successfully.');
        $response->headers->setCookie($refreshCookie->forget());

        return $response;
    }

    #[OA\Post(
        path: '/auth/logout-all',
        operationId: 'authLogoutAll',
        summary: 'Fermer toutes les sessions',
        description: 'Révoque tous les refresh tokens de l’utilisateur authentifié.',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Sessions fermées', content: new OA\JsonContent(ref: '#/components/schemas/AuthLogoutAll200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function logoutAll(Request $request, TokenService $tokenService, \App\Services\Audit\AuditLogger $auditLogger): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tokenService->revokeAllForUser($user);

        auth('api')->logout();

        $auditLogger->security(
            \App\Enums\SecurityEventType::LogoutAll,
            $user,
            request: $request,
        );

        return ApiResponse::success(null, 'All sessions have been closed.');
    }

    #[OA\Post(
        path: '/auth/forgot-password',
        operationId: 'authForgotPassword',
        summary: 'Mot de passe oublié',
        description: 'Envoie un lien de réinitialisation si l’email existe. Réponse volontairement neutre.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alice@example.com'),
                ],
                example: ['email' => 'alice@example.com'],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Demande traitée', content: new OA\JsonContent(ref: '#/components/schemas/AuthForgotPassword200')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return ApiResponse::success(
            null,
            'If the email exists, a password reset link has been sent.',
        );
    }

    #[OA\Post(
        path: '/auth/reset-password',
        operationId: 'authResetPassword',
        summary: 'Réinitialiser le mot de passe',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'reset-token-from-email'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alice@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NewPassword1!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NewPassword1!'),
                ],
                example: [
                    'token' => 'reset-token-from-email',
                    'email' => 'alice@example.com',
                    'password' => 'NewPassword1!',
                    'password_confirmation' => 'NewPassword1!',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe réinitialisé', content: new OA\JsonContent(ref: '#/components/schemas/AuthResetPassword200')),
            new OA\Response(response: 422, description: 'Token ou données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function resetPassword(ResetPasswordRequest $request, \App\Services\Audit\AuditLogger $auditLogger): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($auditLogger, $request): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                event(new PasswordReset($user));

                $auditLogger->security(
                    \App\Enums\SecurityEventType::PasswordReset,
                    $user,
                    request: $request,
                );
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return ApiResponse::success(null, 'Password has been reset successfully.');
    }

    #[OA\Post(
        path: '/auth/email/resend',
        operationId: 'authResendVerification',
        summary: 'Renvoyer l’email de vérification',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Email envoyé ou déjà vérifié', content: new OA\JsonContent(ref: '#/components/schemas/AuthResendVerification200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(null, 'Verification email sent.');
    }

    #[OA\Post(
        path: '/auth/email/verify',
        operationId: 'authVerifyEmail',
        summary: 'Vérifier l’adresse email',
        description: 'Active le compte (status pending → active) via id + hash (sha1(email)).',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id', 'hash'],
                properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '019fc7e1-c324-7380-a855-84056ddd751c'),
                    new OA\Property(property: 'hash', type: 'string', description: 'sha1(email)', example: 'c3ab8ff13720e8ad9047dd39466b3c8974e592c2'),
                ],
                example: [
                    'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                    'hash' => 'c3ab8ff13720e8ad9047dd39466b3c8974e592c2',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Email vérifié', content: new OA\JsonContent(ref: '#/components/schemas/AuthVerifyEmail200')),
            new OA\Response(response: 400, description: 'Hash invalide', content: new OA\JsonContent(ref: '#/components/schemas/ErrorBadRequest400')),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'uuid'],
            'hash' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->find($request->string('id'));

        if ($user === null) {
            return ApiResponse::error('Invalid verification link.', 404);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->string('hash'))) {
            return ApiResponse::error('Invalid verification link.', 400);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return ApiResponse::success([
            'user' => new UserResource($user->fresh()),
        ], 'Email verified successfully.');
    }
}
