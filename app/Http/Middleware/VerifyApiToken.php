<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiToken
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

        $token = $request->header('X-API-Token');
        $clients = config('services.api_keys', []);

        if (! $token) {
            abort(401, 'Unauthorised');
        }

        foreach ($clients as $clientName => $configuredToken) {
            if (
                ! empty($configuredToken) &&
                hash_equals($configuredToken, $token)
            ) {
                $request->attributes->set('api_client', $clientName);

                return $next($request);
            }
        }

        abort(401, 'Unauthorised');
    }
}