<?php

namespace App\Services\GoogleSheets\Resources;

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
    public function saveResource($filename, $resourcePath, $data)
    {

        // Determine the full path for the resource file.
        $path = storage_path(rtrim($resourcePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename);

        // Determine the temporary path for atomic updates.
        $temporaryPath = $path.'.tmp';

        // Ensure the directory for the resource file exists.
        File::ensureDirectoryExists(dirname($path));

        // Write the content to a temporary file to ensure atomic updates.
        if (file_put_contents($temporaryPath, json_encode($data), LOCK_EX) === false) {
            throw new \RuntimeException('Could not write the temporary file.');
        }

        // Replace the original file with the temporary file.
        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException('Could not replace the resource file.');
        }

        // Clear the cache for the updated resource.
        Cache::forget(pathinfo($filename, PATHINFO_FILENAME));

        try {
            // Purge the Hub cache using the LiteSpeedPurgeService.
            $this->liteSpeedPurgeService->purgeHub();
        } catch (\Throwable $e) {
            Log::error('Hub cache purge failed after resource update.', [
                'resource' => $filename,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
