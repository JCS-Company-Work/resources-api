<?php

namespace App\Services\Resources\Update;

use App\Services\LiteSpeedPurgeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ResourceSaveService
{
    /**
     * The LiteSpeed purge service instance.
     *
     * @var LiteSpeedPurgeService
     */
    protected $liteSpeedPurgeService;

    /**
     * Create a new ResourceSaveService instance.
     */
    public function __construct(LiteSpeedPurgeService $liteSpeedPurgeService)
    {
        $this->liteSpeedPurgeService = $liteSpeedPurgeService;
    }

    /**
     * Save a resource to the storage and update the cache.
     *
     * @param  string  $filename
     * @param  string  $resourcePath
     * @param  array  $data
     *
     * @throws \RuntimeException
     */
    public function saveResource($filename, $resourcePath, $data, bool $purgeCache = true)
    {
        $contents = json_encode($data);

        if ($contents === false) {
            throw new \RuntimeException('Could not encode the resource as JSON.');
        }

        $this->saveContents($filename, $resourcePath, $contents);

        // Clear the cache for the updated resource.
        Cache::forget(pathinfo($filename, PATHINFO_FILENAME));

        if ($purgeCache) {
            $this->purge($filename);
        }
    }

    public function saveXmlResource(string $filename, string $resourcePath, string $xml): void
    {
        $this->saveContents($filename, $resourcePath, $xml);
    }

    private function saveContents(string $filename, string $resourcePath, string $contents): void
    {
        $path = storage_path(rtrim($resourcePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename);
        $temporaryPath = $path.'.tmp';

        File::ensureDirectoryExists(dirname($path));

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write the temporary file.');
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException('Could not replace the resource file.');
        }
    }

    /**
     * @return array<string, array{success: bool, message: ?string}>
     */
    public function purge(string $resource): array
    {
        try {
            return $this->liteSpeedPurgeService->purge();
        } catch (\Throwable $e) {
            Log::error('Cache purge failed after resource update.', [
                'resource' => $resource,
                'error' => $e->getMessage(),
            ]);

            return ['error' => ['success' => false, 'message' => 'Cache purge failed. Check the application log.']];
        }
    }
}
