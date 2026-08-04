<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())) {
            return ApiResponse::error(
                'Email address is not verified.',
                403,
            );
        }

        return $next($request);
    }
}
