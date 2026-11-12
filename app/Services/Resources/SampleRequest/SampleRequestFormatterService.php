<?php

namespace App\Services\Resources\SampleRequest;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Formats a brand's sheet rows into the nested JSON structure used by the samples tool.
 */
class SampleRequestFormatterService
{
    /**
     * Per-brand settings. Only the parts that differ between brands live here.
     *
     * @var array<string, array<string, mixed>>
     */
    private const BRANDS = [
        'pt' => [
            'thumbnailBase' => 'https://www.porcelain-tiles.co.uk/wp-content/uploads/',
            'colour' => 'colour',
            'finish' => 'finish',
            'range' => 'range',
            'style' => 'pt',
        ],
        'pp' => [
            'thumbnailBase' => 'https://www.tailormade.uk/wp-content/uploads/',
            'colour' => 'factoryColour',
            'finish' => 'factorySurface',
            'range' => 'ppRange',
            'style' => 'pp',
            'combinedKey' => 'pp',
        ],
        'tm' => [
            'thumbnailBase' => 'https://www.tailormade.uk/wp-content/uploads/',
            'colour' => 'factoryColour',
            'finish' => 'factorySurface',
            'range' => 'ppRange',
            'style' => 'pp',
            'combinedKey' => 'tm',
            'additionalData' => true,
        ],
        'florimuk' => [
            'thumbnailBase' => 'https://www.tailormade.uk/wp-content/uploads/',
            'colour' => 'factoryColour',
            'finish' => 'factorySurface',
            'range' => 'factoryRange',
            'rangeLabel' => 'Factory_Range',
            'style' => 'pp',
            'mapFactory' => false,
            'rangeInClasses' => false,
            'sizesCount' => false,
            'additionalData' => true,
        ],
        'domus' => [
            'thumbnailBase' => 'https://www.tailormade.uk/wp-content/uploads/',
            'colour' => 'factoryColour',
            'finish' => 'factorySurface',
            'range' => 'ppRange',
            'style' => 'pp',
            'mapFactory' => false,
            'rangeInClasses' => false,
            'picklistFromTile' => true,
            'includeFinish' => false,
            'additionalData' => true,
        ],
    ];

    /**
     * Whether a brand has a formatter configuration.
     */
    public function supports(string $brand): bool
    {
        return isset(self::BRANDS[$brand]);
    }

    /**
     * Format one brand's tiles.
     *
     * PT tiles are grouped by range, then "Colour-PostID". Platform Plus style brands are keyed
     * "Colour-Range-PostID". Brands with a combinedKey are also split by factory, the others are flat.
     *
     * @param  array<int, array<string, mixed>>  $tiles  Rows from GoogleSheetsService::getRows()
     * @return array<string, array<string, mixed>>
     */
    public function formatBrand(string $brand, array $tiles): array
    {
        $config = self::BRANDS[$brand] ?? null;

        if ($config === null) {
            return [];
        }

        $tiles = collect($tiles)
            ->filter(fn (array $tile): bool => strtolower($tile['sampleAvailable'] ?? '') === 'true');

        if ($config['style'] === 'pt') {
            return $tiles
                ->groupBy(['range', fn (array $tile): string => $tile['colour'].'-'.$tile['postID']])
                ->map(fn (Collection $colours): array => $colours
                    ->map(fn (Collection $colourTiles): array => $this->buildColour($colourTiles, $config))
                    ->sortKeys()
                    ->all())
                ->sortKeys()
                ->all();
        }

        $colours = $tiles
            ->groupBy(fn (array $tile): string => $tile[$config['colour']].'-'.$tile[$config['range']].'-'.$tile['postID'])
            ->map(fn (Collection $colourTiles): array => $this->buildColour($colourTiles, $config))
            ->sortKeys();

        if (! isset($config['combinedKey'])) {
            return $colours->all();
        }

        $byFactory = $colours
            ->groupBy(fn (array $colour): string => strtolower($colour['Factory']), preserveKeys: true)
            ->map(fn (Collection $group): array => $group->all())
            ->all();

        $byFactory[$config['combinedKey']] = $colours->all();

        return $byFactory;
    }

    /**
     * Platform Plus sells Florim Magnum/Stone as "Florim" and Ergon/Provenza as "Level".
     */
    private function mapFactory(string $factory): string
    {
        if (str_contains($factory, 'Florim')) {
            return 'Florim';
        }

        return in_array(strtolower($factory), ['ergon', 'provenza'], true) ? 'Level' : $factory;
    }

    /**
     * The finish name used as the key for a tile. Platform Plus calls honed "Silk".
     *
     * @param  array<string, mixed>  $tile
     * @param  array<string, mixed>  $config
     */
    private function finishName(array $tile, array $config): string
    {
        $finish = $tile[$config['finish']];

        if ($config['style'] === 'pp' && $finish === 'Honed') {
            $finish = 'Silk';
        }

        return $finish;
    }

    /**
     * Build one colour: shared details from its first tile, plus a node per finish.
     *
     * @param  Collection<int, array<string, mixed>>  $tiles
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildColour(Collection $tiles, array $config): array
    {
        $first = $tiles->first();
        $isPlatformPlus = $config['style'] === 'pp';
        $locations = $config['picklistFromTile'] ?? false ? [] : $first['picklistLocations'] ?? [];

        $finishes = $tiles
            ->groupBy(fn (array $tile): string => str_replace(' ', '-', $this->finishName($tile, $config)))
            ->map(fn (Collection $finishTiles, string $finishKey): array => $this->addPicklistLocations(
                $this->buildFinish($finishTiles, $config),
                $finishKey,
                $finishTiles,
                $locations
            ))
            ->all();

        $factory = $isPlatformPlus && ($config['mapFactory'] ?? true) ? $this->mapFactory($first['factory']) : $first['factory'];

        $colour = [
            'Finishes' => $finishes,
            'Post_ID' => $first['postID'],
            'Colour' => $first[$config['colour']],
            'Factory' => $factory,
            'Shade' => $first['shade'],
        ];

        if ($isPlatformPlus) {
            $colour[$config['rangeLabel'] ?? 'PP_Range'] = $first[$config['range']];
            $colour['PP_Collection'] = $first['ppCollection'];
        } else {
            $colour['Collection_ID'] = $first['collectionID'];
            $colour['Range'] = $first['range'];
        }

        if ($config['additionalData'] ?? false) {
            $colour['additional_data'] = $first['attributes'] ?? [];
        }

        $colour['url'] = $config['thumbnailBase'].$first['thumbnailUrl'];
        $colour['attributes'] = $this->buildAttributes($colour, $config);

        return $colour;
    }

    /**
     * Add a Picklist_Location to a finish when the Locations tab has a matching entry.
     *
     * Locations are keyed by the PT finish name, so every PT finish found on the finish's tiles
     * is checked. This bridges Platform Plus surfaces (e.g. Silk) back to PT finishes (e.g. Honed).
     *
     * @param  array<string, mixed>  $finish
     * @param  Collection<int, array<string, mixed>>  $finishTiles
     * @param  array<string, array<string, string>>  $locations  PT finish => thickness key => location
     * @return array<string, mixed>
     */
    private function addPicklistLocations(array $finish, string $finishKey, Collection $finishTiles, array $locations): array
    {
        $ptFinishes = $finishTiles
            ->map(fn (array $tile): string => str_replace(' ', '-', $tile['finish']))
            ->unique();

        foreach ($ptFinishes as $ptFinish) {
            foreach ($locations[$ptFinish] ?? [] as $thicknessKey => $location) {
                $finish['Picklist_Location'][$finishKey.'-'.$thicknessKey] = $location;
            }
        }

        return $finish;
    }

    /**
     * Build the filter classes used by the frontend to filter and display this colour.
     *
     * @param  array<string, mixed>  $colour
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildAttributes(array $colour, array $config): array
    {
        $finishes = $colour['Finishes'];
        $isPlatformPlus = $config['style'] === 'pp';

        $sizes = array_unique(Arr::flatten(array_column($finishes, 'Size')));
        $thicknesses = array_unique(Arr::flatten(array_column($finishes, 'Thickness')));

        $attributes = $isPlatformPlus ? [] : [
            'rangeClasses' => $this->toClasses([$colour['Range']], 'range'),
            'collectionClasses' => $this->toClasses([$colour['Collection_ID']], 'collection'),
        ];

        $attributes['thicknessClasses'] = $this->toClasses($thicknesses, 'thickness');
        if ($config['sizesCount'] ?? true) {
            $attributes['sizesCount'] = count($sizes);
        }

        $attributes['sizeClasses'] = implode(' ', array_map($this->sizeToClass(...), $sizes));

        if ($isPlatformPlus) {
            $attributes['showOnTM'] = collect($sizes)->contains(fn (string $size): bool => $this->isLargeFormat($size));
        }

        $attributes['factoryClasses'] = $this->toClasses([$colour['Factory']], 'factory');
        $attributes['finishClasses'] = implode(' ', array_map(
            fn (string $finish): string => strtolower(preg_replace('/[()]/', '', str_replace(' ', '-', $finish))),
            array_keys($finishes)
        ));

        $rangeParts = $isPlatformPlus
            ? [strtolower($colour[$config['rangeLabel'] ?? 'PP_Range']), strtolower($colour['PP_Collection'])]
            : [$attributes['rangeClasses'], $attributes['collectionClasses']];

        $classes = [
            strtolower($colour['Colour']),
            ...($isPlatformPlus ? [] : $rangeParts),
            $attributes['thicknessClasses'],
            $attributes['sizeClasses'],
            $attributes['factoryClasses'],
            $attributes['finishClasses'],
            ...($isPlatformPlus && ($config['rangeInClasses'] ?? true) ? $rangeParts : []),
            $colour['Post_ID'],
            strtolower($colour['Shade']),
        ];

        $attributes['classesString'] = implode(' ', array_filter($classes));

        return $attributes;
    }

    /**
     * Large formats (in cm, longest side first) are shown on TailorMade: 150x75, 150x150 or 100x100 and up.
     */
    private function isLargeFormat(string $size): bool
    {
        $dimensions = array_map('intval', explode(' x ', $size));
        array_pop($dimensions);
        $dimensions = array_map(fn (int $dimension): float|int => $dimension / 10, $dimensions);
        rsort($dimensions);

        [$long, $short] = $dimensions + [0, 0];

        return ($long >= 150 && $short >= 75) || ($long >= 100 && $short >= 100);
    }

    /**
     * Turn values into a space-separated class string, e.g. "Marble Slabs" becomes "marble-slabs".
     * Values starting with a number are prefixed with the type, and factories get a "brand-" prefix.
     *
     * @param  array<int, string>  $values
     */
    private function toClasses(array $values, string $type): string
    {
        $classes = array_map(function (string $value) use ($type): string {
            if (is_numeric(substr($value, 0, 1))) {
                $value = $type.'-'.$value;
            }

            $class = str_replace([' ', '.'], '-', strtolower($value));

            return $type === 'factory' && $class !== '' ? 'brand-'.$class : $class;
        }, $values);

        return implode(' ', array_filter($classes));
    }

    /**
     * Convert a size such as "1600 x 3200 x 12" (mm) into a class such as "format-320-x-160".
     */
    private function sizeToClass(string $size): string
    {
        $dimensions = array_map('intval', explode(' x ', $size));

        // The last value is the thickness
        array_pop($dimensions);

        $dimensions = array_map(fn (int $dimension): float|int => $dimension / 10, $dimensions);
        rsort($dimensions);

        return 'format-'.str_replace('.', '', implode('-x-', $dimensions));
    }

    /**
     * Build one finish: sizes, thicknesses and sample options, keyed by thickness.
     *
     * @param  Collection<int, array<string, mixed>>  $tiles
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildFinish(Collection $tiles, array $config): array
    {
        $isPlatformPlus = $config['style'] === 'pp';
        $finish = $isPlatformPlus ? ['Sample_Options_Special' => [], 'Sample_Options_Standard' => []] : [];
        $firstSpecialSize = null;

        foreach ($tiles as $tile) {
            $thicknessKey = str_replace('.', '-', $tile['thickness']).'mm';
            $pieceType = $tile['specialPiece'] ?: $tile['decor'];
            $isBookmatched = ! $isPlatformPlus && str_contains(strtolower($tile['decor']), 'bookmatch block');
            $finishName = $this->finishName($tile, $config);
            $label = str_replace(' ', '-', $finishName).' ('.$tile['thickness'].'mm)';

            if ($pieceType === '') {
                $finish['Size'][$thicknessKey][] = $tile['size'];
                $finish['Sample_Options_Standard'][$thicknessKey][] = $label;
            } elseif ($isBookmatched) {
                $firstSpecialSize ??= [$thicknessKey, $tile['size']];
                // Block A and block B collapse into a single bookmatched entry
                $finish['Special_Size'][$thicknessKey]['Bookmatched'] = $tile['size'];
                $finish['Sample_Options_Special'][$thicknessKey]['Bookmatched'][] = $label.' - Bookmatched';
            } else {
                $firstSpecialSize ??= [$thicknessKey, $tile['size']];
                $finish['Special_Size'][$thicknessKey][$pieceType][] = $tile['size'];
                $finish['Sample_Options_Special'][$thicknessKey][$pieceType][] = $label.' - '.$pieceType;
            }

            $finish['Thickness'][$thicknessKey] = $tile['thickness'];
        }

        // A finish with only special pieces still needs a size for the size filters
        if (! isset($finish['Size']) && $firstSpecialSize !== null) {
            $finish['Size'][$firstSpecialSize[0]][] = $firstSpecialSize[1];
        }

        // Remove duplicate sizes and sample options added by repeated rows
        foreach (['Size', 'Special_Size', 'Sample_Options_Standard', 'Sample_Options_Special'] as $key) {
            if (isset($finish[$key])) {
                $finish[$key] = $this->uniqueLists($finish[$key]);
            }
        }

        $last = $tiles->last();

        if ($isPlatformPlus) {
            if ($config['includeFinish'] ?? true) {
                $finish['Finish'] = str_replace(' ', '-', $this->finishName($tiles->first(), $config));
            }

            $finish['Factory_Surface'] = str_replace(' ', '-', $this->finishName($last, $config));
            $finish['Factory'] = ($config['mapFactory'] ?? true) ? $this->mapFactory($last['factory']) : $last['factory'];
        }

        $finish['R_Rating'] = $last['rRating'] ?? '';

        if (! $isPlatformPlus) {
            $finish['Rectified'] = $last['rectified'] ?? '';
        }

        $finish['Variation'] = $last['variation'] ?? '';

        if ($config['picklistFromTile'] ?? false) {
            foreach ($tiles as $tile) {
                if (($tile['picklistLocation'] ?? '') !== '') {
                    $finishName = str_replace(' ', '-', $this->finishName($tile, $config));
                    $key = $finishName.'-'.str_replace('.', '-', $tile['thickness']).'mm';
                    $finish['Picklist_Location'][$key] ??= $tile['picklistLocation'];
                }
            }

            $finish['Picklist_Location'] ??= '';
        }

        return $finish;
    }

    /**
     * Remove duplicate values from the innermost lists of a nested array.
     *
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, mixed>
     */
    private function uniqueLists(array $values): array
    {
        if (array_is_list($values) && ! is_array($values[0] ?? null)) {
            return array_values(array_unique($values));
        }

        return array_map(
            fn (mixed $value): mixed => is_array($value) ? $this->uniqueLists($value) : $value,
            $values
        );
    }
}
