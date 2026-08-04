<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Actions\Media\CompleteMediaUploadAction;
use App\Actions\Media\CreateMediaSessionAction;
use App\Actions\Media\UploadMediaContentAction;
use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\CompleteMediaUploadRequest;
use App\Http\Requests\Media\CreateMediaSessionRequest;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Media\UploadSessionResource;
use App\Models\Media;
use App\Models\UploadSession;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    #[OA\Post(
        path: '/media/sessions',
        operationId: 'mediaCreateSession',
        summary: 'Créer une session d’upload média',
        description: 'V1 : stockage local chiffré (.enc). Contrat compatible S3 (upload_url).',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Créé', content: new OA\JsonContent(ref: '#/components/schemas/MediaSession201')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function createSession(CreateMediaSessionRequest $request, CreateMediaSessionAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $action->execute($user, $request->validated());

        return ApiResponse::created([
            'media' => new MediaResource($result['media']),
            'session' => new UploadSessionResource($result['session']),
        ], 'Media upload session created.');
    }

    #[OA\Put(
        path: '/media/sessions/{id}/content',
        operationId: 'mediaUploadContent',
        summary: 'Uploader le blob chiffré',
        description: 'Body binaire (application/octet-stream) ou multipart file `file`. Upload chunké via header `Content-Range: bytes START-END/TOTAL`.',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MediaUpload200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function uploadContent(Request $request, string $id, UploadMediaContentAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $session = UploadSession::query()->find($id);

        if ($session === null) {
            return ApiResponse::error('Upload session not found.', 404);
        }

        $range = $this->parseContentRange($request->header('Content-Range'));

        if ($request->hasFile('file')) {
            $session = $action->execute($user, $session, $request->file('file'), $range);
        } else {
            $raw = $request->getContent();
            if ($raw === '' || $raw === false) {
                return ApiResponse::error('Empty upload body.', 422, [
                    'content' => ['Encrypted content is required.'],
                ]);
            }
            $session = $action->execute($user, $session, $raw, $range);
        }

        return ApiResponse::success([
            'session' => new UploadSessionResource($session),
            'media' => new MediaResource($session->media),
        ], 'Encrypted media content stored.');
    }

    /**
     * @return array{start: int, end: int, total: int}|null
     */
    private function parseContentRange(?string $header): ?array
    {
        if ($header === null || $header === '') {
            return null;
        }

        if (preg_match('/bytes\s+(\d+)-(\d+)\/(\d+)/i', $header, $matches) !== 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'content_range' => ['Expected format: bytes START-END/TOTAL.'],
            ]);
        }

        return [
            'start' => (int) $matches[1],
            'end' => (int) $matches[2],
            'total' => (int) $matches[3],
        ];
    }

    #[OA\Post(
        path: '/media/sessions/{id}/complete',
        operationId: 'mediaCompleteSession',
        summary: 'Finaliser l’upload + enveloppes CEK média',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MediaComplete200')),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation422')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function complete(
        CompleteMediaUploadRequest $request,
        string $id,
        CompleteMediaUploadAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $session = UploadSession::query()->find($id);

        if ($session === null) {
            return ApiResponse::error('Upload session not found.', 404);
        }

        $media = $action->execute($user, $session, $request->validated());

        return ApiResponse::success([
            'media' => new MediaResource($media),
        ], 'Media upload completed.');
    }

    #[OA\Get(
        path: '/media/{id}',
        operationId: 'mediaShow',
        summary: 'Métadonnées d’un média',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/MediaShow200')),
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
        $media = $this->findAccessibleMedia($user, $id);

        if ($media === null) {
            return ApiResponse::error('Media not found.', 404);
        }

        return ApiResponse::success([
            'media' => new MediaResource($media),
        ]);
    }

    #[OA\Get(
        path: '/media/{id}/download',
        operationId: 'mediaDownload',
        summary: 'Télécharger le blob chiffré',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Blob chiffré'),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized401')),
            new OA\Response(response: 403, description: 'Email non vérifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden403')),
            new OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound404')),
            new OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorServer500')),
        ],
    )]
    public function download(Request $request, string $id): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $media = $this->findAccessibleMedia($user, $id);

        if ($media === null || $media->status !== MediaStatus::Ready || $media->storage_path === null) {
            return ApiResponse::error('Media not found.', 404);
        }

        $disk = Storage::disk($media->storage_disk ?: 'media');

        if (! $disk->exists($media->storage_path)) {
            return ApiResponse::error('Media file missing.', 404);
        }

        return response()->streamDownload(function () use ($disk, $media): void {
            echo $disk->get($media->storage_path);
        }, ($media->original_filename ?? $media->id).'.enc', [
            'Content-Type' => 'application/octet-stream',
            'X-Content-IV' => $media->content_iv ?? '',
            'X-Media-Checksum' => $media->checksum_sha256 ?? '',
        ]);
    }

    private function findAccessibleMedia(User $user, string $id): ?Media
    {
        $media = Media::query()->find($id);

        if ($media === null) {
            return null;
        }

        $allowed = $media->conversation()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        return $allowed ? $media : null;
    }
}
