<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiToken
{
    /**
     * Build the middleware string restricting a route to one services.api_keys entry.
     */
    public static function using(string $client): string
    {
        return static::class.':'.$client;
    }

    /**
     * Handle an incoming request and verify the API token for the given client.
     */
    public function handle(Request $request, Closure $next, string $client): Response
    {
        $token = $request->header('X-API-Token');
        $configuredToken = config("services.api_keys.{$client}");

        if (! $token || empty($configuredToken) || ! hash_equals($configuredToken, $token)) {
            abort(401, 'Unauthorised');
        }

        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
