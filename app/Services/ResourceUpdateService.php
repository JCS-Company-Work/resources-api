<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use JsonException;

class ResourceUpdateService
{

    /**
     * Allowed resources for updating.
     */
    private const ALLOWED_RESOURCES = [
        'allbrands' => [
            'filepath' => 'app/private/combined-data/',
            'filename' => 'allbrands.json'
        ],
        'allpending' => [
            'filepath' => 'app/private/combined-data/',
            'filename' => 'allpending.json'
        ],
        'florimuk' => [
            'filepath' => 'app/private/florimuk/',
            'filename' => 'florimuk.json'
        ],
        'pt' => [
            'filepath' => 'app/private/samples-tool/',
            'filename' => 'pt.json'
        ],
        'domus' => [
            'filepath' => 'app/private/samples-tool/',
            'filename' => 'domus.json'
        ],
    ];

    /**
     * Get the allowed filename for the specified resource.
     *
     * @param string $resource
     * @return string
     */
    public function getAllowedFilename(string $resource): string
    {

        // Check if the resource is allowed and return the corresponding filename.
        if (! isset(self::ALLOWED_RESOURCES[$resource])) {
            throw new \InvalidArgumentException(
                "Resource [{$resource}] is not allowed."
            );
        }

        // Return the allowed filename for the resource.
        return self::ALLOWED_RESOURCES[$resource]['filename'];
    }

    /**
     * Update the specified resource with the provided content.
     *
     * @param string $resource
     * @param string $content
     * @return void
     */
    public function update(string $resource, string $content): void
    {
        // Get the allowed filename for the resource.
        $filename = $this->getAllowedFilename($resource);

        // Decode the JSON content into an associative array.
        try {
            $data = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new \InvalidArgumentException(
                'Invalid JSON supplied.',
                0,
                $e
            );
        }

        // Ensure the decoded data is an array or object.
        if (! is_array($data)) {
            throw new \InvalidArgumentException(
                'The JSON must contain an array or object.'
            );
        }

        // Prepare the paths for the resource file and its temporary counterpart.
        $path = storage_path(self::ALLOWED_RESOURCES[$resource]['filepath'] . $filename);
        $temporaryPath = $path . '.tmp';

        // Ensure the directory for the resource file exists.
        File::ensureDirectoryExists(dirname($path));

        // Write the content to a temporary file to ensure atomic updates.
        if (file_put_contents($temporaryPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write the temporary file.');
        }

        // Replace the original file with the temporary file.
        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException('Could not replace the resource file.');
        }

        // Clear the cache for the updated resource.
        Cache::forget($resource);
    }

}
