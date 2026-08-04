<?php

namespace App\Http\Middleware;

use App\Enums\SecurityEventType;
use App\Enums\LogSeverity;
use App\Services\Audit\AuditLogger;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            if ($user !== null) {
                $this->auditLogger->security(
                    SecurityEventType::UnauthorizedAccess,
                    $user,
                    ['reason' => 'admin_required', 'path' => $request->path()],
                    LogSeverity::Warning,
                    'failure',
                    module: 'admin',
                    request: $request,
                );
            }

            return ApiResponse::error('Admin access required.', 403);
        }

        return $next($request);
    }
}
