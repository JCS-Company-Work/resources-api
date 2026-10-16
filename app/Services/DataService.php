<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DataService
{

    /**
     * Get data from the cache or JSON file based on the filename
     *
     * @param string $filename The name of the JSON file (without the .json extension)
     * @param string $filepath The path to the JSON file (optional, defaults to storage/app/private/)
     * @return array
     */
    public function getData(string $filepath = 'app/private/', string $filename): array
    {

        // Attempt to retrieve the products from the cache first
        return Cache::remember(
            $filename,
            now()->addDays(30),

            // If the cache does not have the products, read from the JSON file
            function () use ($filename, $filepath) {

                // Read the products from the JSON file if not found in the cache
                $path = storage_path($filepath . $filename . '.json');

                // Ensure the JSON file exists before attempting to read it
                return json_decode(
                    file_get_contents($path),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        );
    }
}