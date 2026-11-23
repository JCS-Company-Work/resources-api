<?php

namespace App\Services\Resources\Data;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;

class XmlResourceService
{
    /**
     * Supported brands and their corresponding XML files.
     */
    public const BRAND_FILES = [
        'platform-plus' => 'platform-plus.xml',
        'tailor-made' => 'tailor-made.xml',
        'porcelain-tiles' => 'porcelain-tiles.xml',
    ];

    /**
     * Brands and the contexts in which they appear.
     */
    private const BRAND_APPEARS_ON = [
        'platform-plus' => ['all', 'platformplus', 'platformplus-tailormade'],
        'tailor-made' => ['all', 'tailormade', 'platformplus-tailormade', 'porcelaintiles-tailormade'],
        'porcelain-tiles' => ['all', 'porcelaintiles-tailormade'],
    ];

    /**
     * Base URLs for ambient resources for each brand.
     */
    private const AMBIENT_BASE_URLS = [
        'platform-plus' => 'https://tailormade.uk/wp-content/uploads/',
        'tailor-made' => 'https://tailormade.uk/wp-content/uploads/',
        'porcelain-tiles' => 'https://porcelain-tiles.co.uk/wp-content/uploads/',
    ];

    /**
     * TM6 range order for sorting products.
     */
    private const TM6_RANGE_ORDER = ['marmi', 'onici', 'precious', 'pietre', 'cemento', 'metalli'];

    /**
     * Generate the legacy colours XML for one brand.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function generate(string $brand, array $rows): string
    {
        $this->assertSupportedBrand($brand);

        $products = [];
        $attributeGroups = [];

        foreach ($rows as $row) {
            $sourceRow = $row;
            $row = $this->mergeAttributeData($row);

            if (! $this->belongsToBrand($brand, $row)) {
                continue;
            }

            $postId = (string) $this->value($row, 'postID');
            $factoryColour = (string) $this->value($row, 'factoryColour');

            if ($postId === '' || $factoryColour === '') {
                continue;
            }

            $productKey = $factoryColour.'-'.$postId;
            $products[$productKey][] = $row;
            $collection = (string) $this->value($sourceRow, 'ppCollection');
            $range = (string) $this->value($sourceRow, 'ppRange');
            $attributeGroups[$collection][$range][] = [
                'source' => array_diff_key($sourceRow, ['attributes' => true]),
                'productKey' => $productKey,
                'row' => $row,
            ];
        }

        $attributeRows = $this->legacyAttributeRows($attributeGroups);
        $products = array_values($products);
        usort($products, fn (array $left, array $right): int => $this->compareProducts($left[0], $right[0]));

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElement('colours');
        $document->appendChild($root);

        foreach ($products as $productRows) {
            $productKey = (string) $this->value($productRows[0], 'factoryColour').'-'.(string) $this->value($productRows[0], 'postID');
            $root->appendChild($this->createColourNode($document, $brand, $productRows, $attributeRows[$productKey] ?? $productRows[0]));
        }

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException("Could not generate XML for brand [{$brand}].");
        }

        return $xml;
    }

    /**
     * Determine if the service supports a given brand.
     * @param  string  $brand
     * @return bool
     */
    public function supports(string $brand): bool
    {
        return array_key_exists($brand, self::BRAND_FILES);
    }

    /**
     * Get the resource path for a given brand.
     * @param  string  $brand
     * @return string
     */
    public function resourcePath(string $brand): string
    {
        $this->assertSupportedBrand($brand);

        return 'xml/'.self::BRAND_FILES[$brand];
    }

    /**
     * Determine if a row belongs to a given brand.
     * @param  string  $brand
     * @param  array<string, mixed>  $row
     * @return bool
     */
    private function belongsToBrand(string $brand, array $row): bool
    {
        $appearsOn = strtolower((string) $this->value($row, 'appearsOn'));
        $pendingItem = strtolower(trim((string) $this->value($row, 'pendingItem')));
        $note = strtolower((string) $this->value($row, 'note'));

        return in_array($appearsOn, self::BRAND_APPEARS_ON[$brand], true)
            && $pendingItem !== 'yes'
            && ! str_contains($note, 'discontinued');
    }

    /**
     * Merge attribute data into the row.
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mergeAttributeData(array $row): array
    {
        $attributes = $row['attributes'] ?? null;

        if (is_array($attributes)) {
            return array_replace($row, $attributes);
        }

        return $row;
    }

    /**
     * @param  array<string, array<string, array<int, array{source: array<string, mixed>, productKey: string, row: array<string, mixed>}>>>  $groups
     * @return array<string, array<string, mixed>>
     */
    private function legacyAttributeRows(array $groups): array
    {
        $attributeRows = [];
        $seenProductCodes = [];

        foreach ($groups as $collection => $ranges) {
            if ($collection === 'TM6') {
                uksort($ranges, static function (string $left, string $right): int {
                    $legacyRangeOrder = ['Marmi', 'Onici', 'Precious', 'Pietre', 'Cemento', 'Metalli'];

                    return (int) array_search($left, $legacyRangeOrder) <=> (int) array_search($right, $legacyRangeOrder);
                });
            }

            foreach ($ranges as $entries) {
                $colours = array_map(fn (array $entry): string => (string) $this->value($entry['source'], 'factoryColour'), $entries);
                $sourceRows = array_column($entries, 'source');
                array_multisort($colours, SORT_ASC, $sourceRows, SORT_ASC, $entries);

                foreach ($entries as $entry) {
                    $productCode = (string) ($entry['source']['productCode'] ?? $entry['source']['product_code'] ?? '');
                    if ($productCode !== '' && isset($seenProductCodes[$productCode])) {
                        continue;
                    }

                    if ($productCode !== '') {
                        $seenProductCodes[$productCode] = true;
                    }
                    $attributeRows[$entry['productKey']] ??= $entry['row'];
                }
            }
        }

        return $attributeRows;
    }

    /**
     * Create a colour node for the given brand and rows.
     * @param  DOMDocument  $document
     * @param  string  $brand
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $attributeRow
     * @return DOMElement
     */
    private function createColourNode(DOMDocument $document, string $brand, array $rows, array $attributeRow): DOMElement
    {
        $colour = $document->createElement('colour');

        foreach ($this->attributes($attributeRow) as $name => $value) {
            $colour->setAttribute($name, $value);
        }

        $this->appendTitles($document, $colour, $rows);
        $this->appendFinishes($document, $colour, $rows);
        $this->appendSizes($document, $colour, $rows);
        $this->appendAmbients($document, $colour, $brand, $rows);
        $this->appendAlternateColours($document, $colour, $rows);
        $this->appendImages($document, $colour, $rows, 'graphics', 'graphic', 0);
        $this->appendImages($document, $colour, $rows, 'faces', 'face', 1);
        $this->appendImages($document, $colour, $rows, 'faces_two', 'face', 2);
        $this->appendFinishSizes($document, $colour, $rows);

        return $colour;
    }

    /**
     * Extract and normalize attribute data from the row.
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function attributes(array $row): array
    {
        $category = strtolower(trim(
            (string) $this->value($row, 'ppCollection').' '.(string) $this->value($row, 'ppRange')
        ));

        if (str_contains($category, 'bookmatch')) {
            $categories = explode(' ', $category);
            $bookmatchPosition = array_search('bookmatch', $categories);
            unset($categories[$bookmatchPosition]);
            array_unshift($categories, 'bookmatch');
            $category = implode(' ', $categories);
        }

        $attributes = [
            'category' => $category,
            'tag' => strtolower(trim((string) $this->value($row, 'shade').' '.(string) $this->value($row, 'range'))),
            'thickness' => strtolower(trim((string) $this->value($row, 'thickness').'mm')),
        ];

        if (strtolower((string) $this->value($row, 'rectified')) === 'yes') {
            $attributes['rectified'] = 'yes';
        }

        $attributes['id'] = (string) $this->value($row, 'postID');

        return $attributes;
    }

    /**
     * Append title nodes to the colour element based on the rows.
     * @param  DOMDocument  $document
     * @param  DOMElement  $colour
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function appendTitles(DOMDocument $document, DOMElement $colour, array $rows): void
    {
        $titles = [];

        foreach ($rows as $row) {
            $factory = strtolower((string) $this->value($row, 'factory'));
            $factory = in_array($factory, ['ergon', 'provenza'], true) ? 'level' : $factory;
            $factoryColour = (string) $this->value($row, 'factoryColour');
            $ptColour = (string) $this->value($row, 'colour');

            if ($factory !== '' && $factoryColour !== '') {
                $titles[$factory] ??= $factoryColour;
            }

            if ($ptColour !== '') {
                $titles['pt'] ??= $ptColour;
            }

            $additionalTitles = $this->value($row, 'titles');

            if (is_array($additionalTitles)) {
                foreach ($additionalTitles as $titleFactory => $title) {
                    if (is_scalar($title) && (string) $title !== '') {
                        $titles[strtolower((string) $titleFactory)] ??= (string) $title;
                    }
                }
            }
        }

        if ($titles === []) {
            return;
        }

        $titlesNode = $document->createElement('titles');
        foreach ($titles as $factory => $title) {
            $titleNode = $this->appendTextElement($document, $titlesNode, 'title', $title);
            $titleNode->setAttribute('factory', $factory);
        }
        $colour->appendChild($titlesNode);
    }

    /**
     * Append finish nodes to the colour element based on the rows.
     * @param  DOMDocument  $document
     * @param  DOMElement  $colour
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function appendFinishes(DOMDocument $document, DOMElement $colour, array $rows): void
    {
        $finishes = [];

        foreach ($rows as $row) {
            $sheetFinish = (string) $this->value($row, 'finish');
            $factoryFinish = (string) $this->value($row, 'factorySurface');
            $finish = $factoryFinish !== ''
                ? $factoryFinish
                : (strtolower($sheetFinish) === 'honed' ? 'Silk' : $sheetFinish);

            if ($finish === '') {
                continue;
            }

            $finishes[$finish] ??= [
                'ptfinish' => (string) ($this->value($row, 'ptfinish') ?: $sheetFinish ?: $finish),
                'factoryfinish' => $factoryFinish !== '' ? $factoryFinish : $finish,
                'r-rating' => (string) $this->value($row, 'rRating'),
            ];
        }

        asort($finishes);

        if ($finishes === []) {
            return;
        }

        $finishesNode = $document->createElement('finishes');
        foreach ($finishes as $name => $attributes) {
            $finishNode = $this->appendTextElement($document, $finishesNode, 'finish', (string) $name);

            foreach ($attributes as $attribute => $value) {
                if ($value !== '') {
                    $finishNode->setAttribute($attribute, $value);
                }
            }
        }
        $colour->appendChild($finishesNode);
    }

    /**
     * Append size nodes to the colour element based on the rows.
     * @param  DOMDocument  $document
     * @param  DOMElement  $colour
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function appendSizes(DOMDocument $document, DOMElement $colour, array $rows): void
    {
        $sizes = [];

        foreach ($rows as $row) {
            $size = $this->formatSize((string) $this->value($row, 'size'));

            if ($size !== '') {
                $sizes[$size] = $size;
            }
        }

        $this->appendSizeNodes($document, $colour, 'sizes', $sizes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function appendAmbients(DOMDocument $document, DOMElement $colour, string $brand, array $rows): void
    {
        $ambients = [];

        foreach ($rows as $row) {
            $ambient = trim((string) ($this->value($row, 'ambient') ?: $this->value($row, 'thumbnailUrl')));

            if ($ambient === '') {
                continue;
            }

            if (! preg_match('#^https?://#i', $ambient)) {
                $ambient = self::AMBIENT_BASE_URLS[$brand].ltrim($ambient, '/');
            }

            $ambients[$ambient] = $ambient;
        }

        if ($ambients === []) {
            return;
        }

        $ambientsNode = $document->createElement('ambients');
        foreach ($ambients as $ambient) {
            $this->appendTextElement($document, $ambientsNode, 'ambient', $ambient);
        }
        $colour->appendChild($ambientsNode);
    }

    /**
     * Append ambient nodes to the colour element based on the rows.
     * @param  DOMDocument  $document
     * @param  DOMElement  $colour
     * @param  string  $brand
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function appendAlternateColours(DOMDocument $document, DOMElement $colour, array $rows): void
    {
        $alternateColours = [];

        foreach ($rows as $row) {
            $alternateColour = trim((string) $this->value($row, 'altcolours'));

            if ($alternateColour !== '') {
                $alternateColours[$alternateColour] = $alternateColour;
            }
        }

        if ($alternateColours !== []) {
            $this->appendTextElement($document, $colour, 'altcolours', implode(', ', $alternateColours));
        }
    }

    /**
     * Append image nodes to the colour element based on the rows.
     * @param  DOMDocument  $document
     * @param  DOMElement  $colour
     * @param  array<int, array<string, mixed>>  $rows
     * @param  string  $field
     * @param  string  $childName
     * @param  int  $formatIndex
     */
    private function appendImages(
        DOMDocument $document,
        DOMElement $colour,
        array $rows,
        string $field,
        string $childName,
        int $formatIndex
    ): void {
        $images = [];
        $formats = [];

        foreach ($rows as $row) {
            $images = [...$images, ...$this->imageData($this->value($row, $field))];
            $formatParts = explode(',', (string) $this->value($row, 'graphicsFormat'));

            if (isset($formatParts[$formatIndex]) && trim($formatParts[$formatIndex]) !== '') {
                $formats[trim($formatParts[$formatIndex])] = trim($formatParts[$formatIndex]);
            }
        }

        if ($images === []) {
            return;
        }

        $parentName = $field === 'facesTwo' ? 'faces_two' : $field;
        $parent = $document->createElement($parentName);
        if (count($formats) === 1) {
            $parent->setAttribute('format', (string) reset($formats));
        }

        foreach ($images as $image) {
            $child = $document->createElement($childName);

            foreach (['small', 'large'] as $sizeName) {
                if (! isset($image[$sizeName])) {
                    continue;
                }

                $sizeNode = $document->createElement($sizeName);
                foreach (['url', 'size'] as $valueName) {
                    $value = (string) ($image[$sizeName][$valueName] ?? '');
                    if ($value !== '') {
                        $this->appendTextElement($document, $sizeNode, $valueName, $value);
                    }
                }
                $child->appendChild($sizeNode);
            }

            $parent->appendChild($child);
        }

        $colour->appendChild($parent);
    }

    /**
     * Append finish size nodes to the colour element based on the rows.
     * @param  DOMDocument  $document
     * @param  DOMElement  $colour
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function appendFinishSizes(DOMDocument $document, DOMElement $colour, array $rows): void
    {
        $sizesByFinish = [];

        foreach ($rows as $row) {
            $finish = (string) $this->value($row, 'factorySurface');
            if ($finish === '') {
                $finish = (string) $this->value($row, 'finish');
                if (strtolower($finish) === 'honed') {
                    $finish = 'Silk';
                }
            }

            $size = $this->formatSize((string) $this->value($row, 'size'));
            if ($finish !== '' && $size !== '') {
                $name = $this->finishSizeElementName($finish);
                $sizesByFinish[$name][$size] = $size;
            }
        }

        ksort($sizesByFinish);
        foreach ($sizesByFinish as $name => $sizes) {
            $this->appendSizeNodes($document, $colour, $name, $sizes);
        }
    }

    /**
     * Generate a valid element name for a finish size.
     * @param  string  $finish
     * @return string
     */
    private function finishSizeElementName(string $finish): string
    {
        $name = 'sizes_'.preg_replace('/[\s-]+/', '_', strtolower(trim($finish)));
        $name = preg_replace('/[\s\/]+/', '_', $name) ?? $name;
        $name = preg_replace('/[^A-Za-z0-9._-]/', '', $name) ?? $name;

        return preg_replace('/_+/', '_', $name) ?? $name;
    }

    /**
     * @param  array<string, string>  $sizes
     * @param  DOMDocument  $document
     * @param  DOMElement  $parent
     * @param  string  $name
     * @param  array<string, string>  $sizes
     */
    private function appendSizeNodes(DOMDocument $document, DOMElement $parent, string $name, array $sizes): void
    {
        if ($sizes === []) {
            return;
        }

        $sizes = array_values($sizes);
        usort($sizes, fn (string $left, string $right): int => $this->compareSizes($left, $right));

        $sizesNode = $document->createElement($name);
        foreach ($sizes as $size) {
            $this->appendTextElement($document, $sizesNode, 'size', $size);
        }
        $parent->appendChild($sizesNode);
    }

    /**
     * Parse image data from a string value.
     * @param  mixed  $value
     * @return array<int, array<string, array{url: string, size: string}>>
     */
    private function imageData(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $images = [];

        foreach (explode(';', $value) as $image) {
            $parts = array_map('trim', explode(',', $image));

            if (count($parts) >= 4) {
                $images[] = [
                    'small' => ['url' => $parts[0], 'size' => $parts[1]],
                    'large' => ['url' => $parts[2], 'size' => $parts[3]],
                ];
            }
        }

        return $images;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  $key
     * @return mixed
     */
    private function value(array $row, string $key): mixed
    {
        $aliases = [
            'altcolours' => ['altcolours', 'altColours', 'alt_colours'],
            'appearsOn' => ['appearsOn', 'appears_on'],
            'factoryColour' => ['factoryColour', 'factory_colour'],
            'factorySurface' => ['factorySurface', 'factory_surface', 'surface'],
            'graphicsFormat' => ['graphicsFormat', 'graphics_format'],
            'pendingItem' => ['pendingItem', 'pending_item'],
            'postID' => ['postID', 'post_id'],
            'ppCollection' => ['ppCollection', 'pp_collection'],
            'ppRange' => ['ppRange', 'pp_range'],
            'rRating' => ['rRating', 'r_rating'],
            'facesTwo' => ['facesTwo', 'faces_two'],
            'thumbnailUrl' => ['thumbnailUrl', 'thumbnail_url', 'ambient'],
        ];

        foreach ($aliases[$key] ?? [$key] as $alias) {
            if (array_key_exists($alias, $row)) {
                return $row[$alias];
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return int
     */
    private function compareProducts(array $left, array $right): int
    {
        $leftCollection = strtolower((string) $this->value($left, 'ppCollection'));
        $rightCollection = strtolower((string) $this->value($right, 'ppCollection'));

        if ($leftCollection === 'tm6' && $rightCollection === 'tm6') {
            $leftRange = array_search(strtolower((string) $this->value($left, 'ppRange')), self::TM6_RANGE_ORDER, true);
            $rightRange = array_search(strtolower((string) $this->value($right, 'ppRange')), self::TM6_RANGE_ORDER, true);

            $rangeComparison = ($leftRange === false ? PHP_INT_MAX : $leftRange)
                <=> ($rightRange === false ? PHP_INT_MAX : $rightRange);

            if ($rangeComparison !== 0) {
                return $rangeComparison;
            }
        }

        return [
            $leftCollection,
            strtolower((string) $this->value($left, 'ppRange')),
            strtolower((string) $this->value($left, 'factoryColour')),
        ] <=> [
            $rightCollection,
            strtolower((string) $this->value($right, 'ppRange')),
            strtolower((string) $this->value($right, 'factoryColour')),
        ];
    }

    /**
     * Compare two size strings.
     * @param  string  $left
     * @param  string  $right
     * @return int
     */
    private function compareSizes(string $left, string $right): int
    {
        $leftNumeric = $this->extractNumeric($left);
        $rightNumeric = $this->extractNumeric($right);

        return $leftNumeric === $rightNumeric
            ? strnatcasecmp($right, $left)
            : $rightNumeric <=> $leftNumeric;
    }

    /**
     * Extract the numeric part of a size string.
     * @param  string  $value
     * @return float
     */
    private function extractNumeric(string $value): float
    {
        preg_match_all('/\d+(?:\.\d+)?/', $value, $matches);

        return $matches[0] === [] ? 0.0 : max(array_map('floatval', $matches[0]));
    }

    /**
     * Format a size string.
     * @param  string  $size
     * @return string
     */
    private function formatSize(string $size): string
    {
        if ($size === '') {
            return '';
        }

        $dimensions = explode('x', strtolower($size));
        array_pop($dimensions);
        $dimensions = array_map(static fn (string $dimension): int => (int) (intval(trim($dimension)) / 10), $dimensions);
        rsort($dimensions);

        return implode(' x ', $dimensions);
    }

    /**
     * Append a text element to a parent element.
     * @param  DOMDocument  $document
     * @param  DOMElement  $parent
     * @param  string  $name
     * @param  string  $value
     * @return DOMElement
     */
    private function appendTextElement(DOMDocument $document, DOMElement $parent, string $name, string $value): DOMElement
    {
        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode($value));
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Assert that a brand is supported.
     * @param  string  $brand
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertSupportedBrand(string $brand): void
    {
        if (! $this->supports($brand)) {
            throw new InvalidArgumentException("Unsupported XML brand [{$brand}].");
        }
    }
}
