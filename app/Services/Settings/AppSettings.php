<?php

namespace App\Services\Settings;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class AppSettings
{
    public const MEDIA_KEY = 'media';

    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array{
     *     max_files: int,
     *     max_file_bytes: int,
     *     max_total_bytes: int,
     *     chunk_bytes: int
     * }
     */
    public function mediaLimits(): array
    {
        return Cache::remember(
            $this->cacheKey(self::MEDIA_KEY),
            self::CACHE_TTL_SECONDS,
            function (): array {
                $defaults = $this->defaultMediaLimits();
                $stored = AppSetting::query()->find(self::MEDIA_KEY)?->value;

                if (! is_array($stored)) {
                    return $defaults;
                }

                return [
                    'max_files' => (int) ($stored['max_files'] ?? $defaults['max_files']),
                    'max_file_bytes' => (int) ($stored['max_file_bytes'] ?? $defaults['max_file_bytes']),
                    'max_total_bytes' => (int) ($stored['max_total_bytes'] ?? $defaults['max_total_bytes']),
                    'chunk_bytes' => (int) ($stored['chunk_bytes'] ?? $defaults['chunk_bytes']),
                ];
            },
        );
    }

    /**
     * Marge pour tag GCM + métadonnées sur le blob chiffré.
     */
    public function maxEncryptedBytes(): int
    {
        return $this->mediaLimits()['max_file_bytes'] + (2 * 1024 * 1024);
    }

    /**
     * @param  array{
     *     max_files?: int,
     *     max_file_bytes?: int,
     *     max_total_bytes?: int,
     *     chunk_bytes?: int
     * }  $limits
     * @return array{
     *     max_files: int,
     *     max_file_bytes: int,
     *     max_total_bytes: int,
     *     chunk_bytes: int
     * }
     */
    public function updateMediaLimits(array $limits): array
    {
        $current = $this->mediaLimits();
        $merged = [
            'max_files' => (int) ($limits['max_files'] ?? $current['max_files']),
            'max_file_bytes' => (int) ($limits['max_file_bytes'] ?? $current['max_file_bytes']),
            'max_total_bytes' => (int) ($limits['max_total_bytes'] ?? $current['max_total_bytes']),
            'chunk_bytes' => (int) ($limits['chunk_bytes'] ?? $current['chunk_bytes']),
        ];

        AppSetting::query()->updateOrCreate(
            ['key' => self::MEDIA_KEY],
            ['value' => $merged],
        );

        Cache::forget($this->cacheKey(self::MEDIA_KEY));

        return $this->mediaLimits();
    }

    /**
     * @return array{
     *     max_files: int,
     *     max_file_bytes: int,
     *     max_total_bytes: int,
     *     chunk_bytes: int
     * }
     */
    public function defaultMediaLimits(): array
    {
        /** @var array<string, int> $config */
        $config = config('fichochat.media', []);

        return [
            'max_files' => (int) ($config['max_files'] ?? 10),
            'max_file_bytes' => (int) ($config['max_file_bytes'] ?? 52_428_800),
            'max_total_bytes' => (int) ($config['max_total_bytes'] ?? 104_857_600),
            'chunk_bytes' => (int) ($config['chunk_bytes'] ?? 1_048_576),
        ];
    }

    private function cacheKey(string $key): string
    {
        return 'app_settings:'.$key;
    }
}
