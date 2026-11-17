<?php

namespace App\Services;

use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class LiteSpeedPurgeService
{
    /**
     * Request a LiteSpeed cache purge for each configured site.
     *
     * @return array<string, array{success: bool, message: ?string}>
     */
    public function purge(): array
    {
        $results = [];

        foreach (config('services.cache_purge') as $site => $settings) {
            try {
                if (empty($settings['url']) || empty($settings['token'])) {
                    throw new RuntimeException('Cache purge configuration is missing.');
                }

                $response = Http::withHeaders([
                    'X-Purge-Token' => $settings['token'],
                    'Accept' => 'application/json',
                ])
                    ->timeout(15)
                    ->post($settings['url']);

                if (! $response->successful()) {
                    throw new RuntimeException('Cache purge returned HTTP '.$response->status().'.');
                }

                if ($response->json('success') !== true) {
                    throw new RuntimeException('The site did not confirm the cache purge request.');
                }

                $message = $response->json('message');
                $results[$site] = [
                    'success' => true,
                    'message' => is_string($message) ? $message : null,
                ];

                Log::info('LiteSpeed cache purge requested.', ['site' => $site]);
            } catch (ConnectionException|InvalidArgumentException|RuntimeException $exception) {
                Log::error('LiteSpeed cache purge failed.', [
                    'site' => $site,
                    'error' => $exception->getMessage(),
                ]);

                $results[$site] = [
                    'success' => false,
                    'message' => $exception instanceof ConnectionException || $exception instanceof InvalidArgumentException || $exception instanceof TransferException
                        ? 'Cache purge request failed. Check the application log.'
                        : $exception->getMessage(),
                ];
            }
        }

        return $results;
    }
}
