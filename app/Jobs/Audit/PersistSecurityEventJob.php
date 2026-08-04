<?php

namespace App\Jobs\Audit;

use App\Models\SecurityEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PersistSecurityEventJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes)
    {
        $this->onQueue('audits');
    }

    public function handle(): void
    {
        try {
            SecurityEvent::query()->create($this->attributes);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist security event (job).', [
                'type' => $this->attributes['type'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
