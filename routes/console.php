<?php

use App\Jobs\Media\CleanupStaleUploadSessionsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('media:cleanup-stale-sessions', function () {
    CleanupStaleUploadSessionsJob::dispatchSync();
    $this->info('Stale upload sessions cleaned.');
})->purpose('Expire les sessions d’upload dépassées (médias)');