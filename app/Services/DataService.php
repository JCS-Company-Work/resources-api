<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DataService
{

    /**
     * Get all products from the cache or JSON file
     *
     * @return array
     */
    public function getProducts(): array
    {

        // Attempt to retrieve the products from the cache first
        return Cache::remember(
            'allbrands.json',
            now()->addMinutes(10),

            // If the cache does not have the products, read from the JSON file
            function () {

                // Read the products from the JSON file if not found in the cache
                $path = storage_path('app/private/allbrands.json');

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