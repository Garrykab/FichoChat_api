<?php

namespace App\Jobs\Audit;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class PersistActivityLogJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $logName,
        public string $event,
        public string $description,
        public ?string $causerId,
        public ?string $subjectType,
        public ?string $subjectId,
        public array $properties,
    ) {
        $this->onQueue('audits');
    }

    public function handle(): void
    {
        try {
            $logger = activity($this->logName)
                ->event($this->event)
                ->withProperties($this->properties);

            if ($this->causerId !== null) {
                $user = User::query()->find($this->causerId);
                if ($user !== null) {
                    $logger->causedBy($user);
                }
            }

            $subject = $this->resolveSubject($this->subjectType, $this->subjectId);
            if ($subject !== null) {
                $logger->performedOn($subject);
            } elseif ($this->subjectType !== null && $this->subjectId !== null) {
                $logger->tap(function (Activity $activity): void {
                    $activity->subject_type = $this->subjectType;
                    $activity->subject_id = $this->subjectId;
                });
            }

            $logger->log($this->description);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist Spatie activity log (job).', [
                'type' => $this->event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveSubject(?string $subjectType, ?string $subjectId): ?Model
    {
        if ($subjectType === null || $subjectId === null || ! class_exists($subjectType)) {
            return null;
        }

        if (! is_subclass_of($subjectType, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $subjectType */
        $query = $subjectType::query();

        if (method_exists($subjectType, 'withTrashed')) {
            /** @phpstan-ignore-next-line */
            $query = $subjectType::withTrashed();
        }

        return $query->find($subjectId);
    }
}
