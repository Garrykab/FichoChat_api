<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'FichoChat API',
    description: 'API REST versionnée de FichoChat. Chaque endpoint documente des exemples JSON complets de réponses (succès et erreurs).',
)]
#[OA\Server(url: '/api/v1', description: 'API v1')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT Access Token obtenu via /auth/login ou /auth/register. Header: Authorization: Bearer {access_token}',
)]
#[OA\Tag(name: 'Auth', description: 'Authentification, sessions et vérification email')]
#[OA\Tag(name: 'Users', description: 'Profils, préférences et recherche utilisateurs')]

/**
 * Schémas d'erreur réutilisables (exemples complets).
 */
#[OA\Schema(
    schema: 'ErrorValidation422',
    description: 'Erreur de validation',
    type: 'object',
    example: [
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => [
            'email' => ['The email has already been taken.'],
            'password' => ['The password field confirmation does not match.'],
        ],
    ],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed.'),
        new OA\Property(property: 'errors', type: 'object', example: ['email' => ['The email has already been taken.']]),
    ],
)]
#[OA\Schema(
    schema: 'ErrorForbidden403',
    description: 'Accès refusé (email non vérifié, etc.)',
    type: 'object',
    example: [
        'success' => false,
        'message' => 'Email address is not verified.',
    ],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Email address is not verified.'),
    ],
)]
#[OA\Schema(
    schema: 'ErrorUnauthorized401',
    description: 'Non authentifié',
    type: 'object',
    example: [
        'success' => false,
        'message' => 'Unauthenticated.',
    ],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ],
)]
#[OA\Schema(
    schema: 'ErrorBadRequest400',
    description: 'Requête invalide',
    type: 'object',
    example: [
        'success' => false,
        'message' => 'Invalid verification link.',
    ],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Invalid verification link.'),
    ],
)]
#[OA\Schema(
    schema: 'ErrorNotFound404',
    description: 'Ressource introuvable',
    type: 'object',
    example: [
        'success' => false,
        'message' => 'User not found.',
    ],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'User not found.'),
    ],
)]
#[OA\Schema(
    schema: 'ErrorServer500',
    description: 'Erreur serveur',
    type: 'object',
    example: [
        'success' => false,
        'message' => 'Internal server error.',
    ],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Internal server error.'),
    ],
)]

/**
 * Exemples de succès Auth.
 */
#[OA\Schema(
    schema: 'AuthRegister201',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Account created successfully. Please verify your email.',
        'data' => [
            'user' => [
                'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'username' => 'tmp_abcdefghijkl',
                'email' => 'alice@example.com',
                'status' => 'pending',
                'email_verified_at' => null,
                'profile_setup_completed' => false,
                'created_at' => '2026-08-03T13:48:09.000000Z',
                'updated_at' => '2026-08-03T13:48:09.000000Z',
            ],
            'tokens' => [
                'access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAiLCJpYXQiOjE3ODU3NjQ4ODksImV4cCI6MTc4NTc2ODQ4OSwic3ViIjoiMDE5ZmM3ZTEtYzMyNC03MzgwLWE4NTUtODQwNTZkZGQ3NTFjIn0.example',
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'refresh_token' => 'QrIrX3Y54yjPNh1c2f8TsqxiqfhqwDGKKS0ZwheVFO9W1Fh9I0TPlbg5IyumLPqo',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AuthLogin200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Logged in successfully.',
        'data' => [
            'user' => [
                'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'username' => 'alice',
                'email' => 'alice@example.com',
                'status' => 'active',
                'email_verified_at' => '2026-08-03T14:00:00.000000Z',
                'created_at' => '2026-08-03T13:48:09.000000Z',
                'updated_at' => '2026-08-03T14:00:00.000000Z',
            ],
            'tokens' => [
                'access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.exampleAccessToken',
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'refresh_token' => 'a8f3c2e19b7d4e6f0a1b2c3d4e5f6789abcdef0123456789abcdef0123456789',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AuthMe200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'user' => [
                'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'username' => 'alice',
                'email' => 'alice@example.com',
                'status' => 'active',
                'email_verified_at' => '2026-08-03T14:00:00.000000Z',
                'created_at' => '2026-08-03T13:48:09.000000Z',
                'updated_at' => '2026-08-03T14:00:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AuthRefresh200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Token refreshed successfully.',
        'data' => [
            'tokens' => [
                'access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.newAccessToken',
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'refresh_token' => 'b9e4d3f20c8e5f7a1b2c3d4e5f6789abcdef0123456789abcdef0123456790',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AuthLogout200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Logged out successfully.',
        'data' => null,
    ],
)]
#[OA\Schema(
    schema: 'AuthLogoutAll200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'All sessions have been closed.',
        'data' => null,
    ],
)]
#[OA\Schema(
    schema: 'AuthForgotPassword200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'If the email exists, a password reset link has been sent.',
        'data' => null,
    ],
)]
#[OA\Schema(
    schema: 'AuthResetPassword200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Password has been reset successfully.',
        'data' => null,
    ],
)]
#[OA\Schema(
    schema: 'AuthVerifyEmail200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Email verified successfully.',
        'data' => [
            'user' => [
                'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'username' => 'alice',
                'email' => 'alice@example.com',
                'status' => 'active',
                'email_verified_at' => '2026-08-03T14:10:00.000000Z',
                'created_at' => '2026-08-03T13:48:09.000000Z',
                'updated_at' => '2026-08-03T14:10:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AuthResendVerification200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Verification email sent.',
        'data' => null,
    ],
)]
#[OA\Schema(
    schema: 'AuthLoginInvalidCredentials422',
    type: 'object',
    example: [
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => [
            'login' => ['The provided credentials are incorrect.'],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AuthRefreshInvalid422',
    type: 'object',
    example: [
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => [
            'refresh_token' => ['The refresh token is invalid or expired.'],
        ],
    ],
)]

/**
 * Exemples de succès Users.
 */
#[OA\Schema(
    schema: 'UsersMe200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'user' => [
                'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'username' => 'alice',
                'email' => 'alice@example.com',
                'status' => 'active',
                'email_verified_at' => '2026-08-03T14:00:00.000000Z',
                'profile' => [
                    'display_name' => 'Alice',
                    'bio' => 'Bonjour FichoChat',
                    'avatar_url' => 'http://localhost:8000/storage/avatars/019fc7e1/avatar.jpg',
                    'locale' => 'fr',
                    'theme' => 'system',
                    'updated_at' => '2026-08-03T15:00:00.000000Z',
                ],
                'created_at' => '2026-08-03T13:48:09.000000Z',
                'updated_at' => '2026-08-03T14:00:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'UsersUpdateProfile200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Profile updated successfully.',
        'data' => [
            'user' => [
                'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'username' => 'alice',
                'email' => 'alice@example.com',
                'status' => 'active',
                'email_verified_at' => '2026-08-03T14:00:00.000000Z',
                'profile' => [
                    'display_name' => 'Alice Doe',
                    'bio' => 'Hello FichoChat',
                    'avatar_url' => 'http://localhost:8000/storage/avatars/019fc7e1/avatar.jpg',
                    'locale' => 'fr',
                    'theme' => 'dark',
                    'updated_at' => '2026-08-03T16:20:00.000000Z',
                ],
                'created_at' => '2026-08-03T13:48:09.000000Z',
                'updated_at' => '2026-08-03T14:00:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'UsersChangePassword200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Password updated successfully.',
        'data' => null,
    ],
)]
#[OA\Schema(
    schema: 'UsersSearch200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Users retrieved successfully.',
        'data' => [
            'users' => [
                [
                    'id' => '019fc7e2-1111-2222-3333-444455556666',
                    'username' => 'alice42',
                    'display_name' => 'Alice',
                    'bio' => 'Disponible',
                    'avatar_url' => null,
                ],
            ],
        ],
        'meta' => [
            'current_page' => 1,
            'per_page' => 15,
            'total' => 1,
            'last_page' => 1,
        ],
    ],
)]
#[OA\Schema(
    schema: 'UsersShow200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'user' => [
                'id' => '019fc7e2-1111-2222-3333-444455556666',
                'username' => 'publicuser',
                'display_name' => 'Public User',
                'bio' => 'Visible bio',
                'avatar_url' => null,
            ],
        ],
    ],
)]
#[OA\Tag(name: 'Devices', description: 'Enregistrement, validation et révocation des appareils')]
#[OA\Schema(
    schema: 'Device',
    type: 'object',
    example: [
        'id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
        'name' => 'MacBook Pro',
        'platform' => 'web',
        'fingerprint' => 'a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456',
        'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
        'status' => 'approved',
        'approved_at' => '2026-08-03T18:00:00.000000Z',
        'approved_by_device_id' => null,
        'revoked_at' => null,
        'last_seen_at' => '2026-08-03T18:05:00.000000Z',
        'created_at' => '2026-08-03T18:00:00.000000Z',
        'updated_at' => '2026-08-03T18:05:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'DevicesIndex200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'devices' => [
                [
                    'id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'name' => 'MacBook Pro',
                    'platform' => 'web',
                    'fingerprint' => 'a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456',
                    'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                    'status' => 'approved',
                    'approved_at' => '2026-08-03T18:00:00.000000Z',
                    'approved_by_device_id' => null,
                    'revoked_at' => null,
                    'last_seen_at' => '2026-08-03T18:05:00.000000Z',
                    'created_at' => '2026-08-03T18:00:00.000000Z',
                    'updated_at' => '2026-08-03T18:05:00.000000Z',
                ],
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'DevicesRegister201',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Device registered successfully.',
        'data' => [
            'device' => [
                'id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'name' => 'MacBook Pro',
                'platform' => 'web',
                'fingerprint' => 'a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456',
                'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                'status' => 'approved',
                'approved_at' => '2026-08-03T18:00:00.000000Z',
                'approved_by_device_id' => null,
                'revoked_at' => null,
                'last_seen_at' => '2026-08-03T18:00:00.000000Z',
                'created_at' => '2026-08-03T18:00:00.000000Z',
                'updated_at' => '2026-08-03T18:00:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'DevicesShow200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'device' => [
                'id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'name' => 'iPhone 15',
                'platform' => 'ios',
                'fingerprint' => 'bbccddeeff00112233445566778899aabbccddeeff00112233445566778899aa',
                'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                'status' => 'pending',
                'approved_at' => null,
                'approved_by_device_id' => null,
                'revoked_at' => null,
                'last_seen_at' => '2026-08-03T18:10:00.000000Z',
                'created_at' => '2026-08-03T18:10:00.000000Z',
                'updated_at' => '2026-08-03T18:10:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'DevicesPending200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'devices' => [
                [
                    'id' => '019fc7e1-1111-2222-3333-444455556666',
                    'name' => 'iPhone 15',
                    'platform' => 'ios',
                    'fingerprint' => 'bbccddeeff00112233445566778899aabbccddeeff00112233445566778899aa',
                    'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                    'status' => 'pending',
                    'approved_at' => null,
                    'approved_by_device_id' => null,
                    'revoked_at' => null,
                    'last_seen_at' => '2026-08-03T18:10:00.000000Z',
                    'created_at' => '2026-08-03T18:10:00.000000Z',
                    'updated_at' => '2026-08-03T18:10:00.000000Z',
                ],
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'DevicesApprove200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Device approved successfully.',
        'data' => [
            'device' => [
                'id' => '019fc7e1-1111-2222-3333-444455556666',
                'name' => 'iPhone 15',
                'platform' => 'ios',
                'fingerprint' => 'bbccddeeff00112233445566778899aabbccddeeff00112233445566778899aa',
                'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                'status' => 'approved',
                'approved_at' => '2026-08-03T18:15:00.000000Z',
                'approved_by_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'revoked_at' => null,
                'last_seen_at' => '2026-08-03T18:10:00.000000Z',
                'created_at' => '2026-08-03T18:10:00.000000Z',
                'updated_at' => '2026-08-03T18:15:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'DevicesRevoke200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Device revoked successfully.',
        'data' => [
            'device' => [
                'id' => '019fc7e1-1111-2222-3333-444455556666',
                'name' => 'iPhone 15',
                'platform' => 'ios',
                'fingerprint' => 'bbccddeeff00112233445566778899aabbccddeeff00112233445566778899aa',
                'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                'status' => 'revoked',
                'approved_at' => '2026-08-03T18:15:00.000000Z',
                'approved_by_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'revoked_at' => '2026-08-03T19:00:00.000000Z',
                'last_seen_at' => '2026-08-03T18:10:00.000000Z',
                'created_at' => '2026-08-03T18:10:00.000000Z',
                'updated_at' => '2026-08-03T19:00:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'DevicesReplaceKey200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Device key replaced successfully.',
        'data' => [
            'device' => [
                'id' => '019fc7e1-1111-2222-3333-444455556666',
                'name' => 'iPhone 15',
                'platform' => 'ios',
                'fingerprint' => 'ccddeeff00112233445566778899aabbccddeeff00112233445566778899aabb',
                'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                'status' => 'approved',
                'approved_at' => '2026-08-03T18:15:00.000000Z',
                'approved_by_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'revoked_at' => null,
                'last_seen_at' => '2026-08-03T18:10:00.000000Z',
                'created_at' => '2026-08-03T18:10:00.000000Z',
                'updated_at' => '2026-08-03T19:30:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Tag(name: 'Keys', description: 'Clés publiques, enveloppes CEK et rotation — jamais de clé privée')]
#[OA\Schema(
    schema: 'KeyEnvelope',
    type: 'object',
    example: [
        'id' => '019fc9e1-aaaa-bbbb-cccc-ddddeeeeffff',
        'content_type' => 'sync',
        'content_id' => '019fc8e2-aaaa-bbbb-cccc-ddddeeeeffff',
        'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
        'recipient_device_id' => '019fc7e1-1111-2222-3333-444455556666',
        'encrypted_cek' => 'base64-ciphertext…',
        'ephemeral_public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
        'iv' => 'base64-iv…',
        'algorithm' => 'ECDH-P256+HKDF-SHA256+AES-256-GCM',
        'key_version' => 1,
        'created_at' => '2026-08-03T19:30:00.000000Z',
        'updated_at' => '2026-08-03T19:30:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'KeysPublicList200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'devices' => [
                [
                    'device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                    'name' => 'MacBook Pro',
                    'platform' => 'web',
                    'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                    'fingerprint' => 'a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456',
                    'status' => 'approved',
                ],
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'KeysEnvelopesStore201',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Key envelopes stored successfully.',
        'data' => [
            'envelopes' => [
                [
                    'id' => '019fc9e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'content_type' => 'sync',
                    'content_id' => '019fc8e2-aaaa-bbbb-cccc-ddddeeeeffff',
                    'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'recipient_device_id' => '019fc7e1-1111-2222-3333-444455556666',
                    'encrypted_cek' => 'base64-ciphertext…',
                    'ephemeral_public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                    'iv' => 'base64-iv…',
                    'algorithm' => 'ECDH-P256+HKDF-SHA256+AES-256-GCM',
                    'key_version' => 1,
                    'created_at' => '2026-08-03T19:30:00.000000Z',
                    'updated_at' => '2026-08-03T19:30:00.000000Z',
                ],
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'KeysEnvelopesList200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'envelopes' => [
                [
                    'id' => '019fc9e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'content_type' => 'sync',
                    'content_id' => '019fc8e2-aaaa-bbbb-cccc-ddddeeeeffff',
                    'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'recipient_device_id' => '019fc7e1-1111-2222-3333-444455556666',
                    'encrypted_cek' => 'base64-ciphertext…',
                    'ephemeral_public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYH...\n-----END PUBLIC KEY-----',
                    'iv' => 'base64-iv…',
                    'algorithm' => 'ECDH-P256+HKDF-SHA256+AES-256-GCM',
                    'key_version' => 1,
                    'created_at' => '2026-08-03T19:30:00.000000Z',
                    'updated_at' => '2026-08-03T19:30:00.000000Z',
                ],
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'KeysRotate200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Device key rotated successfully.',
        'data' => [
            'device' => [
                'id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'name' => 'MacBook Pro',
                'platform' => 'web',
                'fingerprint' => 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff',
                'public_key' => '-----BEGIN PUBLIC KEY-----\nNEWkey...\n-----END PUBLIC KEY-----',
                'status' => 'approved',
                'approved_at' => '2026-08-03T18:00:00.000000Z',
                'approved_by_device_id' => null,
                'revoked_at' => null,
                'last_seen_at' => '2026-08-03T19:45:00.000000Z',
                'created_at' => '2026-08-03T18:00:00.000000Z',
                'updated_at' => '2026-08-03T19:45:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Tag(name: 'Conversations', description: 'Conversations privées 1-1, participants, archivage')]
#[OA\Schema(
    schema: 'Conversation',
    type: 'object',
    example: [
        'id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
        'type' => 'private',
        'created_by_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
        'last_message_at' => null,
        'my_status' => 'active',
        'my_role' => 'owner',
        'participants' => [
            [
                'id' => '019fca01-1111-2222-3333-444455556666',
                'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                'user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'role' => 'owner',
                'status' => 'active',
                'archived_at' => null,
                'hidden_at' => null,
                'last_read_at' => null,
                'user' => [
                    'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                    'username' => 'alice',
                    'display_name' => 'Alice',
                    'bio' => null,
                    'avatar_url' => null,
                ],
                'created_at' => '2026-08-03T20:00:00.000000Z',
                'updated_at' => '2026-08-03T20:00:00.000000Z',
            ],
        ],
        'created_at' => '2026-08-03T20:00:00.000000Z',
        'updated_at' => '2026-08-03T20:00:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'ConversationsIndex200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'conversations' => [
                [
                    'id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                    'type' => 'private',
                    'created_by_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                    'last_message_at' => null,
                    'my_status' => 'active',
                    'my_role' => 'owner',
                    'participants' => [],
                    'created_at' => '2026-08-03T20:00:00.000000Z',
                    'updated_at' => '2026-08-03T20:00:00.000000Z',
                ],
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'ConversationsStore201',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Conversation created successfully.',
        'data' => [
            'conversation' => [
                'id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                'type' => 'private',
                'created_by_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'last_message_at' => null,
                'my_status' => 'active',
                'my_role' => 'owner',
                'participants' => [],
                'created_at' => '2026-08-03T20:00:00.000000Z',
                'updated_at' => '2026-08-03T20:00:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'ConversationsShow200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'conversation' => [
                'id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                'type' => 'private',
                'created_by_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'last_message_at' => null,
                'my_status' => 'active',
                'my_role' => 'owner',
                'participants' => [],
                'created_at' => '2026-08-03T20:00:00.000000Z',
                'updated_at' => '2026-08-03T20:00:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'ConversationsStatus200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Conversation updated successfully.',
        'data' => [
            'conversation' => [
                'id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                'type' => 'private',
                'created_by_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'last_message_at' => null,
                'my_status' => 'archived',
                'my_role' => 'owner',
                'participants' => [],
                'created_at' => '2026-08-03T20:00:00.000000Z',
                'updated_at' => '2026-08-03T20:10:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Tag(name: 'Messages', description: 'Messages chiffrés, édition, suppression, accusés — jamais de clair')]
#[OA\Schema(
    schema: 'Message',
    type: 'object',
    example: [
        'id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
        'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
        'sender_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
        'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
        'type' => 'text',
        'ciphertext' => 'base64…',
        'iv' => 'base64…',
        'reply_to_message_id' => null,
        'edited_at' => null,
        'deleted_for_everyone' => false,
        'sender' => [
            'id' => '019fc7e1-c324-7380-a855-84056ddd751c',
            'username' => 'alice',
            'display_name' => 'Alice',
            'bio' => null,
            'avatar_url' => null,
        ],
        'receipts' => [],
        'medias' => [],
        'created_at' => '2026-08-03T20:20:00.000000Z',
        'updated_at' => '2026-08-03T20:20:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'MessagesIndex200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'messages' => [
                [
                    'id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
                    'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                    'sender_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                    'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'type' => 'text',
                    'ciphertext' => 'base64…',
                    'iv' => 'base64…',
                    'reply_to_message_id' => null,
                    'edited_at' => null,
                    'deleted_for_everyone' => false,
                    'receipts' => [],
                    'medias' => [],
                    'created_at' => '2026-08-03T20:20:00.000000Z',
                    'updated_at' => '2026-08-03T20:20:00.000000Z',
                ],
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'MessagesStore201',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Message sent successfully.',
        'data' => [
            'message' => [
                'id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
                'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                'sender_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'type' => 'text',
                'ciphertext' => 'base64…',
                'iv' => 'base64…',
                'reply_to_message_id' => null,
                'edited_at' => null,
                'deleted_for_everyone' => false,
                'receipts' => [],
                'medias' => [],
                'created_at' => '2026-08-03T20:20:00.000000Z',
                'updated_at' => '2026-08-03T20:20:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'MessagesShow200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'message' => [
                'id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
                'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                'sender_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
                'sender_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'type' => 'text',
                'ciphertext' => 'base64…',
                'iv' => 'base64…',
                'reply_to_message_id' => null,
                'edited_at' => null,
                'deleted_for_everyone' => false,
                'receipts' => [],
                'medias' => [],
                'created_at' => '2026-08-03T20:20:00.000000Z',
                'updated_at' => '2026-08-03T20:20:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'MessagesReceipt200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Receipt updated successfully.',
        'data' => [
            'receipt' => [
                'id' => '019fcb02-aaaa-bbbb-cccc-ddddeeeeffff',
                'message_id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
                'user_id' => '019fc7e2-1111-2222-3333-444455556666',
                'device_id' => '019fc7e1-1111-2222-3333-444455556666',
                'status' => 'read',
                'delivered_at' => '2026-08-03T20:21:00.000000Z',
                'read_at' => '2026-08-03T20:21:05.000000Z',
                'created_at' => '2026-08-03T20:20:00.000000Z',
                'updated_at' => '2026-08-03T20:21:05.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'MessagesDeleteForMe200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Message deleted for you.',
        'data' => [
            'message_id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
            'deleted_for_me' => true,
        ],
    ],
)]
#[OA\Tag(name: 'Media', description: 'Upload médias chiffrés (V1 disque local, contrat prêt S3)')]
#[OA\Schema(
    schema: 'Media',
    type: 'object',
    example: [
        'id' => '019fcc01-aaaa-bbbb-cccc-ddddeeeeffff',
        'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
        'message_id' => null,
        'uploader_user_id' => '019fc7e1-c324-7380-a855-84056ddd751c',
        'uploader_device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
        'type' => 'image',
        'mime_type' => 'image/png',
        'original_filename' => 'photo.png',
        'size_bytes' => 1024,
        'encrypted_size_bytes' => 1040,
        'checksum_sha256' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'content_iv' => 'base64…',
        'status' => 'ready',
        'created_at' => '2026-08-03T20:30:00.000000Z',
        'updated_at' => '2026-08-03T20:30:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'UploadSession',
    type: 'object',
    example: [
        'id' => '019fcc02-aaaa-bbbb-cccc-ddddeeeeffff',
        'media_id' => '019fcc01-aaaa-bbbb-cccc-ddddeeeeffff',
        'status' => 'open',
        'bytes_received' => 0,
        'expires_at' => '2026-08-03T21:30:00.000000Z',
        'upload_url' => 'http://localhost:8000/api/v1/media/sessions/019fcc02-aaaa-bbbb-cccc-ddddeeeeffff/content',
        'created_at' => '2026-08-03T20:30:00.000000Z',
        'updated_at' => '2026-08-03T20:30:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'MediaSession201',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Media upload session created.',
        'data' => [
            'media' => [
                'id' => '019fcc01-aaaa-bbbb-cccc-ddddeeeeffff',
                'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'pending',
                'type' => 'image',
                'mime_type' => 'image/png',
            ],
            'session' => [
                'id' => '019fcc02-aaaa-bbbb-cccc-ddddeeeeffff',
                'media_id' => '019fcc01-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'open',
                'upload_url' => 'http://localhost:8000/api/v1/media/sessions/019fcc02-…/content',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'MediaUpload200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Encrypted media content stored.',
        'data' => [
            'session' => [
                'id' => '019fcc02-aaaa-bbbb-cccc-ddddeeeeffff',
                'bytes_received' => 1040,
                'status' => 'open',
            ],
            'media' => [
                'id' => '019fcc01-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'uploading',
                'encrypted_size_bytes' => 1040,
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'MediaComplete200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Media upload completed.',
        'data' => [
            'media' => [
                'id' => '019fcc01-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'ready',
                'content_iv' => 'base64…',
                'checksum_sha256' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'MediaShow200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'media' => [
                'id' => '019fcc01-aaaa-bbbb-cccc-ddddeeeeffff',
                'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                'type' => 'image',
                'status' => 'ready',
                'mime_type' => 'image/png',
                'content_iv' => 'base64…',
            ],
        ],
    ],
)]
#[OA\Tag(name: 'Sync', description: 'Synchronisation multiappareil — bootstrap, delta, partage d’enveloppes')]
#[OA\Schema(
    schema: 'SyncState200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'state' => [
                'device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'pending_bootstrap',
                'cursor_at' => null,
                'last_message_id' => null,
                'bootstrap_completed_at' => null,
                'needs_bootstrap' => true,
                'updated_at' => '2026-08-03T21:00:00.000000Z',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'SyncBootstrap200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Sync bootstrap ready.',
        'data' => [
            'state' => [
                'device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'pending_bootstrap',
                'needs_bootstrap' => true,
            ],
            'conversations' => [],
            'messages_by_conversation' => [],
            'medias' => [],
            'envelopes' => [],
            'receipts' => [],
            'generated_at' => '2026-08-03T21:00:00.000000Z',
        ],
    ],
)]
#[OA\Schema(
    schema: 'SyncDelta200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Sync delta ready.',
        'data' => [
            'since' => '2026-08-03T20:00:00.000000Z',
            'conversations' => [],
            'messages' => [],
            'medias' => [],
            'receipts' => [],
            'envelopes' => [],
            'hidden_conversation_ids' => [],
            'generated_at' => '2026-08-03T21:00:00.000000Z',
        ],
    ],
)]
#[OA\Schema(
    schema: 'SyncAck200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Sync cursor updated.',
        'data' => [
            'state' => [
                'device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'ready',
                'cursor_at' => '2026-08-03T21:00:00.000000Z',
                'bootstrap_completed_at' => '2026-08-03T21:00:00.000000Z',
                'needs_bootstrap' => false,
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'SyncMissingEnvelopes200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Missing envelopes listed.',
        'data' => [
            'target_device_id' => '019fc7e1-1111-2222-3333-444455556666',
            'target_public_key' => '-----BEGIN PUBLIC KEY-----\n…',
            'messages' => [
                [
                    'content_type' => 'message',
                    'content_id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
                    'conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                ],
            ],
            'medias' => [],
        ],
    ],
)]
#[OA\Tag(name: 'Realtime', description: 'Pusher Channels — auth privée + config publique')]
#[OA\Schema(
    schema: 'RealtimeConfig200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => [
            'enabled' => true,
            'driver' => 'pusher',
            'key' => 'app-key',
            'cluster' => 'mt1',
            'auth_endpoint' => 'http://localhost:8000/api/v1/broadcasting/auth',
            'force_tls' => true,
        ],
    ],
)]
#[OA\Tag(name: 'Security', description: 'Journalisation sécurité + activité (own user V1)')]
#[OA\Schema(
    schema: 'SecurityEvent',
    type: 'object',
    example: [
        'id' => '019fd001-aaaa-bbbb-cccc-ddddeeeeffff',
        'type' => 'login',
        'severity' => 'info',
        'module' => 'auth',
        'result' => 'success',
        'device_id' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0',
        'context' => null,
        'created_at' => '2026-08-04T02:00:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'ActivityLog',
    type: 'object',
    example: [
        'id' => '019fd002-aaaa-bbbb-cccc-ddddeeeeffff',
        'type' => 'message_sent',
        'severity' => 'info',
        'module' => 'messages',
        'subject_type' => 'App\\Models\\Message',
        'subject_id' => '019fcb01-aaaa-bbbb-cccc-ddddeeeeffff',
        'result' => 'success',
        'device_id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0',
        'context' => ['conversation_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff'],
        'created_at' => '2026-08-04T02:00:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'SecurityEvents200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Security events listed.',
        'data' => [
            'events' => [
                [
                    'id' => '019fd001-aaaa-bbbb-cccc-ddddeeeeffff',
                    'type' => 'login',
                    'severity' => 'info',
                    'module' => 'auth',
                    'result' => 'success',
                    'device_id' => null,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0',
                    'context' => null,
                    'created_at' => '2026-08-04T02:00:00.000000Z',
                ],
            ],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 30,
                'total' => 1,
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'ActivityLogs200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Activity logs listed.',
        'data' => [
            'logs' => [
                [
                    'id' => '019fd002-aaaa-bbbb-cccc-ddddeeeeffff',
                    'type' => 'conversation_created',
                    'severity' => 'info',
                    'module' => 'conversations',
                    'subject_type' => 'App\\Models\\Conversation',
                    'subject_id' => '019fca01-aaaa-bbbb-cccc-ddddeeeeffff',
                    'result' => 'success',
                    'device_id' => null,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0',
                    'context' => ['peer_user_id' => '019fb001-aaaa-bbbb-cccc-ddddeeeeffff'],
                    'created_at' => '2026-08-04T02:00:00.000000Z',
                ],
            ],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 30,
                'total' => 1,
            ],
        ],
    ],
)]
#[OA\Tag(name: 'Settings', description: 'Paramètres compte — notifications, confidentialité, apparence')]
#[OA\Schema(
    schema: 'UserSettings',
    type: 'object',
    example: [
        'notifications_enabled' => true,
        'notify_messages' => true,
        'notify_devices' => true,
        'notify_security' => true,
        'hide_message_previews' => false,
        'silent_mode' => false,
        'send_read_receipts' => true,
        'show_last_seen' => true,
        'updated_at' => '2026-08-04T03:00:00.000000Z',
    ],
)]
#[OA\Schema(
    schema: 'SettingsShow200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Settings loaded.',
        'data' => [
            'settings' => [
                'notifications_enabled' => true,
                'notify_messages' => true,
                'notify_devices' => true,
                'notify_security' => true,
                'hide_message_previews' => false,
                'silent_mode' => false,
                'send_read_receipts' => true,
                'show_last_seen' => true,
                'updated_at' => '2026-08-04T03:00:00.000000Z',
            ],
            'appearance' => [
                'locale' => 'fr',
                'theme' => 'system',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'SettingsUpdate200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Settings updated successfully.',
        'data' => [
            'settings' => [
                'notifications_enabled' => true,
                'notify_messages' => true,
                'notify_devices' => false,
                'notify_security' => true,
                'hide_message_previews' => true,
                'silent_mode' => false,
                'send_read_receipts' => true,
                'show_last_seen' => false,
                'updated_at' => '2026-08-04T03:10:00.000000Z',
            ],
            'appearance' => [
                'locale' => 'fr',
                'theme' => 'dark',
            ],
        ],
    ],
)]
#[OA\Tag(name: 'Admin', description: 'Administration plateforme — métadonnées & audit uniquement (jamais le clair des messages)')]
#[OA\Schema(
    schema: 'AdminStats200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Admin stats ready.',
        'data' => [
            'users' => ['total' => 10, 'active' => 8, 'pending' => 1, 'suspended' => 1, 'admins' => 1],
            'devices' => ['total' => 15, 'approved' => 12, 'pending' => 2, 'revoked' => 1],
            'conversations' => 20,
            'messages' => 100,
            'medias' => 5,
            'security_events_24h' => 12,
            'activity_logs_24h' => 40,
            'login_failures_24h' => 2,
            'generated_at' => '2026-08-04T03:30:00.000000Z',
        ],
    ],
)]
#[OA\Schema(
    schema: 'AdminUsers200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Users listed.',
        'data' => [
            'users' => [
                [
                    'id' => '019fb001-aaaa-bbbb-cccc-ddddeeeeffff',
                    'username' => 'alice',
                    'email' => 'alice@example.com',
                    'status' => 'active',
                    'role' => 'user',
                    'devices_count' => 2,
                    'display_name' => 'Alice',
                ],
            ],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 30, 'total' => 1],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AdminUserShow200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'User loaded.',
        'data' => [
            'user' => [
                'id' => '019fb001-aaaa-bbbb-cccc-ddddeeeeffff',
                'username' => 'alice',
                'email' => 'alice@example.com',
                'status' => 'active',
                'role' => 'user',
                'devices_count' => 1,
            ],
            'devices' => [],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AdminUserAction200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'User suspended.',
        'data' => [
            'user' => [
                'id' => '019fb001-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'suspended',
                'role' => 'user',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AdminDevices200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Devices listed.',
        'data' => [
            'devices' => [
                [
                    'id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                    'user_id' => '019fb001-aaaa-bbbb-cccc-ddddeeeeffff',
                    'username' => 'alice',
                    'name' => 'MacBook',
                    'platform' => 'web',
                    'status' => 'approved',
                    'fingerprint' => 'abc123',
                ],
            ],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 30, 'total' => 1],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AdminDeviceRevoke200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Device revoked by admin.',
        'data' => [
            'device' => [
                'id' => '019fc7e1-aaaa-bbbb-cccc-ddddeeeeffff',
                'status' => 'revoked',
            ],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AdminSecurityEvents200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Security events listed.',
        'data' => [
            'events' => [],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 40, 'total' => 0],
        ],
    ],
)]
#[OA\Schema(
    schema: 'AdminActivityLogs200',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Activity logs listed.',
        'data' => [
            'logs' => [],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 40, 'total' => 0],
        ],
    ],
)]
class OpenApiSpec
{
}
