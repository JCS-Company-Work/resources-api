<?php

namespace App\Services\Resources;

use InvalidArgumentException;

class ProductFinderDataService
{
    private const BRANDS = [
        'porcelaintiles' => [
            'appearsOn' => ['porcelaintiles', 'porcelaintiles-tailormade', 'all'],
            'thumbnailBase' => 'https://www.porcelain-tiles.co.uk/wp-content/uploads/',
        ],
        'tailormade' => [
            'appearsOn' => ['platformplus-tailormade', 'porcelaintiles-tailormade', 'all'],
            'thumbnailBase' => 'https://www.porcelain-tiles.co.uk/wp-content/uploads/',
        ],
    ];

    public function supports(string $brand): bool
    {
        return isset(self::BRANDS[$brand]);
    }

    /**
     * Generate the three product finder resources for a brand.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function generate(string $brand, array $rows): array
    {
        if (! $this->supports($brand)) {
            throw new InvalidArgumentException("Unsupported product finder brand [{$brand}].");
        }

        $tiles = $this->filterTiles($brand, $rows);

        return [
            'tile-filter-data' => $this->createMainData($brand, $tiles),
            'tile-filter-ref-data' => $this->createReferenceData($brand, $tiles),
            'filter-panel' => $this->createFilterPanel($brand, $tiles),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterTiles(string $brand, array $rows): array
    {
        $allowedAppearsOn = self::BRANDS[$brand]['appearsOn'];

        return array_values(array_filter(
            array_map(fn (array $row): array => $this->normaliseRow($row, $brand), $rows),
            function (array $tile) use ($allowedAppearsOn): bool {
                return in_array(strtolower($tile['Appears_On']), $allowedAppearsOn, true)
                    && strtolower($tile['Pending_Item']) !== 'yes'
                    && ! empty($tile['Post_ID'])
                    && ! str_contains(strtolower($tile['Note']), 'discontinued by us');
            }
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normaliseRow(array $row, string $brand): array
    {
        $keys = [
            'range' => 'Range',
            'rangeID' => 'Range_ID',
            'colour' => 'Colour',
            'factoryColour' => 'Factory_Colour',
            'shade' => 'Shade',
            'finish' => 'Finish',
            'thickness' => 'Thickness',
            'size' => 'Size',
            'rRating' => 'R_Rating',
            'rectified' => 'Rectified',
            'variation' => 'Variation',
            'specialPiece' => 'Special_Piece',
            'decor' => 'Decor',
            'factory' => 'Factory',
            'sampleAvailable' => 'Sample_Available',
            'picklistLocation' => 'Picklist_Location',
            'collectionID' => 'Collection_ID',
            'ppCollection' => 'PP_Collection',
            'ppRange' => 'PP_Range',
            'postID' => 'Post_ID',
            'thumbnailUrl' => 'Thumbnail_Url',
            'appearsOn' => 'Appears_On',
            'pendingItem' => 'Pending_Item',
            'note' => 'Note',
            'productCode' => 'Product_Code',
        ];

        foreach ($keys as $source => $target) {
            $row[$target] = $row[$source] ?? $row[$target] ?? '';
        }

        $row['Sample_Available'] = filter_var($row['Sample_Available'], FILTER_VALIDATE_BOOLEAN);
        $row['Finish'] = $brand === 'tailormade'
            ? $this->normaliseFinish((string) $row['Finish'])
            : (string) $row['Finish'];
        $row['Factory'] = $this->normaliseFactory((string) $row['Factory']);

        return $row;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiles
     * @return array<string, mixed>
     */
    private function createMainData(string $brand, array $tiles): array
    {
        $products = [];

        foreach ($tiles as $tile) {
            if ($tile['Sample_Available'] !== true) {
                continue;
            }

            $key = 'id-'.$tile['Post_ID'];
            $products[$key] ??= $this->createProduct($brand, $tile);
            $finish = $tile['Finish'];

            if (! isset($products[$key]['Finishes'][$finish])) {
                $products[$key]['Finishes'][$finish] = $this->createFinish($tile);
            } else {
                $this->mergeFinish($products[$key]['Finishes'][$finish], $tile);
            }

            if (str_contains(strtolower((string) $tile['Decor']), 'bookmatch')) {
                $products[$key]['isBookmatched'] = 'bookmatched';
            }
        }

        foreach ($products as &$product) {
            ksort($product['Finishes']);
        }
        unset($product);

        uasort($products, fn (array $a, array $b): int => [$a['Range'], $a['Colour']] <=> [$b['Range'], $b['Colour']]);

        return $products;
    }

    /**
     * @param  array<string, mixed>  $tile
     * @return array<string, mixed>
     */
    private function createProduct(string $brand, array $tile): array
    {
        $collectionNames = [
            '119' => 'Marble',
            '12' => 'Stone',
            '133' => 'Concrete',
            '11' => 'Wood',
            '14' => 'Glass',
            '394' => 'Slabs',
            '15' => 'Metallic',
        ];

        $collectionId = $this->isSlab($tile['Range'])
            ?: (string) $tile['Collection_ID'];
        $collectionName = $collectionNames[$collectionId] ?? '';

        return [
            'Post_ID' => $tile['Post_ID'],
            'Range' => $tile['Range'],
            'Range_ID' => $tile['Range_ID'],
            'Colour' => $tile['Colour'],
            'Factory_Colour' => $tile['Factory_Colour'],
            'Shade' => $tile['Shade'],
            'Factory' => $tile['Factory'],
            'Finish' => $tile['Finish'],
            'Collection_ID' => $collectionId,
            'PP_Collection' => $this->hyphenateClass((string) $tile['PP_Collection'], 'pp_collection'),
            'Collection_Name' => $collectionName,
            'url' => self::BRANDS[$brand]['thumbnailBase'].$this->thumbnailUrl((string) $tile['Thumbnail_Url']),
            'Finishes' => [
                $tile['Finish'] => $this->createFinish($tile),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $tile
     * @return array<string, mixed>
     */
    private function createFinish(array $tile): array
    {
        $finish = [
            'Size' => [],
            'Thickness' => [],
            'Rectified' => $tile['Rectified'],
            'Variation' => $tile['Variation'],
            'Picklist_Location' => $tile['Picklist_Location'],
        ];

        if ($tile['R_Rating'] !== '') {
            $finish['R_Rating'] = [$tile['R_Rating']];
        }

        $this->mergeFinish($finish, $tile);

        return $finish;
    }

    /**
     * @param  array<string, mixed>  $finish
     * @param  array<string, mixed>  $tile
     */
    private function mergeFinish(array &$finish, array $tile): void
    {
        $thicknessKey = str_replace('.', '-', (string) $tile['Thickness']).'mm';
        $isSpecial = $tile['Special_Piece'] !== '' || $tile['Decor'] !== '';
        $pieceType = $tile['Special_Piece'] ?: $tile['Decor'];

        if ($isSpecial) {
            $finish['Special_Size'][$thicknessKey][$pieceType] ??= [];
            if (! in_array($tile['Size'], $finish['Special_Size'][$thicknessKey][$pieceType], true)) {
                $finish['Special_Size'][$thicknessKey][$pieceType][] = $tile['Size'];
            }
        } else {
            $finish['Size'][$thicknessKey] ??= [];
            if (! in_array($tile['Size'], $finish['Size'][$thicknessKey], true)) {
                $finish['Size'][$thicknessKey][] = $tile['Size'];
            }
        }

        $finish['Thickness'][$thicknessKey] = $tile['Thickness'];

        if ($tile['R_Rating'] !== '') {
            $finish['R_Rating'] ??= [];
            if (! in_array($tile['R_Rating'], $finish['R_Rating'], true)) {
                $finish['R_Rating'][] = $tile['R_Rating'];
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiles
     * @return array<int, array<string, string>>
     */
    private function createReferenceData(string $brand, array $tiles): array
    {
        return array_map(function (array $tile) use ($brand): array {
            return [
                'product_code' => strtolower((string) $tile['Product_Code']),
                'range' => strtolower((string) $tile['Range']),
                'range_id' => strtolower((string) $tile['Range_ID']),
                'colour' => strtolower((string) ($brand === 'tailormade' ? $tile['Factory_Colour'] : $tile['Colour'])),
                'shade' => strtolower((string) $tile['Shade']),
                'effect' => strtolower((string) $this->effect($tile, $brand)),
                'finish' => strtolower($this->referenceFinish((string) $tile['Finish'])),
                'thickness' => strtolower((string) $this->thicknessFilter($tile, $brand)),
                'size' => strtolower($this->formatSize((string) $tile['Size'])),
                'r_rating' => strtolower((string) $tile['R_Rating']),
                'variation' => strtolower((string) $tile['Variation']),
                'special_piece' => strtolower((string) $tile['Special_Piece']),
                'decor' => strtolower((string) $tile['Decor']),
                'post_id' => strtolower((string) $tile['Post_ID']),
            ];
        }, $tiles);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiles
     * @return array<string, mixed>
     */
    private function createFilterPanel(string $brand, array $tiles): array
    {
        $references = $this->createReferenceData($brand, $tiles);
        $definitions = [
            'Colour' => 'shade',
            'Effect' => 'effect',
            'Finish' => 'finish',
            'Slip Rating' => 'r_rating',
            'Thickness' => 'thickness',
            'Size' => 'size',
            'Range' => 'range',
            'Slabs' => 'pp_collection',
            'Bookmatched' => 'decor',
        ];
        $panel = [];

        foreach ($definitions as $label => $column) {
            $values = $column === 'size'
                ? array_values(array_unique(array_filter(array_map(
                    fn (array $tile): string => $tile[$column],
                    array_filter($references, fn (array $tile): bool => $tile['special_piece'] === '' && $tile['decor'] === '')
                ))))
                : array_values(array_unique(array_filter(array_column($references, $column))));

            $filters = [];
            foreach ($values as $value) {
                if ($label === 'Finish' && str_contains($value, 'chip')) {
                    continue;
                }

                $filters[$value] = [
                    'label' => $label === 'Thickness'
                        ? preg_replace('/(\d+\.?\d*)(mm)/', '$1 $2', $value)
                        : ($label === 'Size' ? $value.' mm' : ucwords($value)),
                    'toggle-btn' => $label === 'Bookmatched' ? 'bookmatched' : $value,
                ];

                if ($label !== 'Bookmatched') {
                    $filters[$value]['filter-name'] = 'filter-'.str_replace(' ', '-', $value);
                }
            }

            if ($label === 'Bookmatched') {
                $filters = ['Bookmatched' => [
                    'label' => 'Bookmatched',
                    'toggle-btn' => 'bookmatched',
                ]];
            }

            $panel[$label] = [
                'control-group' => $column,
                'filters' => array_values($filters),
            ];
        }

        return $panel;
    }

    private function effect(array $tile, string $brand): string
    {
        if ($brand === 'tailormade') {
            return (string) $tile['PP_Range'];
        }

        return [
            '119' => 'Marble',
            '12' => 'Stone',
            '133' => 'Concrete',
            '11' => 'Wood',
            '14' => 'Glass',
            '394' => 'Slabs',
            '15' => 'Metallic',
        ][(string) $tile['Collection_ID']] ?? '';
    }

    private function thicknessFilter(array $tile, string $brand): string
    {
        return $brand === 'tailormade'
            ? (string) $tile['PP_Collection']
            : (string) $tile['Thickness'].'mm';
    }

    private function formatSize(string $size): string
    {
        $parts = array_map('trim', array_slice(explode('x', strtolower($size), 3), 0, 2));
        rsort($parts, SORT_NUMERIC);

        return implode(' x ', $parts);
    }

    private function normaliseFinish(string $finish): string
    {
        $finish = strtolower(trim($finish));

        $finishMap = [
            'natural' => ['natural', 'naturale', 'comfort', 'matte', 'matte leather', 'matte r10', 'leather', 'velvet leather', 'brushed', 'real matt', 'matte - naturale', 'opaca', 'velvet', 'smooth'],
            'silk' => ['silk', 'matte silk', 'satin', 'soft', 'honed', 'lappato matte'],
            'polished' => ['polished', 'lappato', 'full lappato', 'glossy', 'high-glo', 'high-glossy', 'lucidato', 'lucida', 'glossy mirror', 'lapped'],
            'structured' => ['structured', 'bocciardato', 'strutturato', 'spazzolato', 'grip', '3d', 'active'],
        ];

        foreach ($finishMap as $normalised => $values) {
            if (in_array($finish, $values, true)) {
                return $normalised;
            }
        }

        return $finish;
    }

    private function referenceFinish(string $finish): string
    {
        return in_array(strtolower($finish), ['natural r10', 'natural r11'], true)
            ? 'Natural'
            : $finish;
    }

    private function hyphenateClass(string $value, string $type): string
    {
        if ($value === '') {
            return '';
        }

        $value = is_numeric($value[0]) ? $type.'-'.$value : $value;

        return strtolower(str_replace([' ', '.'], '-', $value));
    }

    private function isSlab(string $range): ?string
    {
        if (! str_contains(strtolower($range), 'slabs')) {
            return null;
        }

        $slabName = trim((string) preg_replace('/\s*slabs\s*/i', '', $range));
        $slabName = $slabName === '4D' ? 'Marble' : $slabName;

        return array_search($slabName, [
            '119' => 'Marble',
            '12' => 'Stone',
            '133' => 'Concrete',
            '11' => 'Wood',
            '14' => 'Glass',
            '394' => 'Slabs',
            '15' => 'Metallic',
        ], true) ?: null;
    }

    private function normaliseFactory(string $factory): string
    {
        return in_array(strtolower($factory), ['ergon', 'provenza'], true) ? 'Level' : $factory;
    }

    private function thumbnailUrl(string $url): string
    {
        return str_replace('700x700', '300x300', $url);
    }
}
