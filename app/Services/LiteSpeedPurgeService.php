<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LiteSpeedPurgeService
{
    /**
     * Purge the LiteSpeed cache on the WordPress Hub.
     */
    public function purgeHub(): void
    {
        $url = config('services.hub_cache_purge.url');
        $token = config('services.hub_cache_purge.token');

        if (! $url || ! $token) {
            throw new RuntimeException(
                'Hub cache purge configuration is missing.'
            );
        }

        $response = Http::withHeaders([
            'X-Purge-Token' => $token,
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->post($url);

        if (! $response->successful()) {
            Log::error('Hub LiteSpeed purge failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                'Hub cache purge returned HTTP '.$response->status()
            );
        }

        if ($response->json('success') !== true) {
            throw new RuntimeException(
                'Hub did not confirm the cache purge.'
            );
        }

        Log::info('Hub LiteSpeed cache purge confirmed.');
    }
}
