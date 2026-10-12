<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyResourceApiToken
{

    /**
     * Handle an incoming request and verify the Resource API token.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     *
     */
    public function handle(Request $request, Closure $next): Response
    {

        // Retrieve the Resource API token from the configuration.
        $token = config('services.resource_api.token');

        // If the token is not set or does not match the request header, return an unauthorized response.
        if (
            ! is_string($token) ||
            $token === '' ||
            ! hash_equals($token, (string) $request->header('X-API-Token', ''))
        ) {
            return response()->json([
                'message' => 'Unauthorised.',
            ], 401);
        }

        return $next($request);
    }
}