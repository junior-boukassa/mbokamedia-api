<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveApiAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('api')?->is_active) {
            return new JsonResponse([
                'success' => false,
                'message' => 'This account is inactive.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
