<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bootstrap admins
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails promoted to admin on login/me (or via artisan).
    | Never grants message content access — admins see metadata & audit only.
    |
    */
    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Media upload limits (defaults)
    |--------------------------------------------------------------------------
    |
    | Runtime values live in `app_settings` (key `media`) and are editable
    | from the admin UI. These defaults apply when the row is missing.
    |
    */
    'media' => [
        'max_files' => (int) env('MEDIA_MAX_FILES', 10),
        'max_file_bytes' => (int) env('MEDIA_MAX_FILE_BYTES', 52_428_800),
        'max_total_bytes' => (int) env('MEDIA_MAX_TOTAL_BYTES', 104_857_600),
        'chunk_bytes' => (int) env('MEDIA_CHUNK_BYTES', 1_048_576),
    ],
];
