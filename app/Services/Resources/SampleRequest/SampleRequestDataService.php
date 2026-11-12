<?php

namespace App\Services\Resources\SampleRequest;

class SampleRequestDataService
{
    /**
     * Mapping of brands to their corresponding Appears_On values in Google Sheets
     */
    private const BRAND_APPEARS_ON = [
        'pt' => ['porcelaintiles', 'porcelaintiles-tailormade', 'all'],
        'pp' => ['platformplus', 'platformplus-tailormade', 'all'],
        'tm' => ['tailormade', 'platformplus-tailormade', 'porcelaintiles-tailormade', 'all'],
        'domus' => ['domus'],
    ];

    /**
     * Get the tiles for a single brand, excluding pending items
     *
     * @param  string  $brand  The brand to filter tiles for
     * @param  array<int, array<string, mixed>>  $tiles
     * @return array<int, array<string, mixed>>
     */
    public function createSampleRequestData(string $brand, array $tiles): array
    {

        // Filter out pending items and tiles that don't belong to the specified brand
        $tiles = array_filter(
            $tiles,
            fn (array $tile): bool => strtolower($tile['pendingItem'] ?? '') !== 'yes'
                && $this->belongsToBrand($brand, $tile)
        );

        // Reindex the array to ensure consecutive numeric keys
        return array_values($tiles);
    }

    /**
     * Determine if a tile belongs to the specified brand
     *
     * @param  array<string, mixed>  $tile
     */
    private function belongsToBrand(string $brand, array $tile): bool
    {
        // Special handling for the 'florimuk' brand
        if ($brand === 'florimuk') {
            return strtolower($tile['florimukItem'] ?? '') === 'true';
        }

        // Check if the brand has a defined mapping in BRAND_APPEARS_ON
        if (! isset(self::BRAND_APPEARS_ON[$brand])) {
            return false;
        }

        // Check if the tile has an 'appearsOn' value
        if (! isset($tile['appearsOn'])) {
            return false;
        }

        // Return true if the tile's 'appearsOn' value matches one of the brand's defined values
        return in_array(
            strtolower($tile['appearsOn'] ?? ''),
            self::BRAND_APPEARS_ON[$brand] ?? [],
            true
        );
    }
}
