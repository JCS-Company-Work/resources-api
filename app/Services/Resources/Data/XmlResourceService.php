<?php

namespace App\Services\Resources\Data;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;

class XmlResourceService
{
    public const BRAND_FILES = [
        'platform-plus' => 'platform-plus.xml',
        'tailor-made' => 'tailor-made.xml',
        'porcelain-tiles' => 'porcelain-tiles.xml',
    ];

    private const BRAND_APPEARS_ON = [
        'platform-plus' => ['all', 'platformplus', 'platformplus-tailormade'],
        'tailor-made' => ['all', 'tailormade', 'platformplus-tailormade', 'porcelaintiles-tailormade'],
        'porcelain-tiles' => ['all', 'porcelaintiles', 'porcelaintiles-tailormade'],
    ];

    private const AMBIENT_BASE_URLS = [
        'platform-plus' => 'https://tailormade.uk/wp-content/uploads/',
        'tailor-made' => 'https://tailormade.uk/wp-content/uploads/',
        'porcelain-tiles' => 'https://porcelain-tiles.co.uk/wp-content/uploads/',
    ];

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

        foreach ($rows as $row) {
            $row = $this->mergeAttributeData($row);

            if (! $this->belongsToBrand($brand, $row)) {
                continue;
            }

            $postId = (string) $this->value($row, 'postID');
            $factoryColour = (string) $this->value($row, 'factoryColour');

            if ($postId === '' || $factoryColour === '') {
                continue;
            }

            $products[$factoryColour.'-'.$postId][] = $row;
        }

        $products = array_values($products);
        usort($products, fn (array $left, array $right): int => $this->compareProducts($left[0], $right[0]));

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElement('colours');
        $document->appendChild($root);

        foreach ($products as $productRows) {
            $root->appendChild($this->createColourNode($document, $brand, $productRows));
        }

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException("Could not generate XML for brand [{$brand}].");
        }

        return $xml;
    }

    public function supports(string $brand): bool
    {
        return array_key_exists($brand, self::BRAND_FILES);
    }

    public function resourcePath(string $brand): string
    {
        $this->assertSupportedBrand($brand);

        return 'xml/'.self::BRAND_FILES[$brand];
    }

    /**
     * @param  array<string, mixed>  $productRows
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
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function createColourNode(DOMDocument $document, string $brand, array $rows): DOMElement
    {
        $colour = $document->createElement('colour');

        foreach ($this->attributes($rows[0]) as $name => $value) {
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
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function attributes(array $row): array
    {
        $attributes = [];
        $rawAttributes = $this->value($row, 'attributes');

        if (is_string($rawAttributes)) {
            $decoded = json_decode($rawAttributes, true);

            if (is_array($decoded)) {
                $attributes = $decoded;
            }
        }

        $attributes['category'] ??= strtolower(trim(
            (string) $this->value($row, 'ppCollection').' '.(string) $this->value($row, 'ppRange')
        ));
        $attributes['tag'] ??= strtolower(trim(
            (string) $this->value($row, 'shade').' '.(string) $this->value($row, 'range')
        ));

        $thickness = trim((string) $this->value($row, 'thickness'));
        $attributes['thickness'] ??= strtolower($thickness === '' || str_ends_with(strtolower($thickness), 'mm')
            ? $thickness
            : $thickness.'mm');

        if (strtolower((string) $this->value($row, 'rectified')) === 'yes') {
            $attributes['rectified'] = 'yes';
        }

        $attributes['id'] = (string) $this->value($row, 'postID');

        return array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            array_intersect_key($attributes, array_flip(['category', 'tag', 'thickness', 'rectified', 'id']))
        );
    }

    /**
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
     * @param  array<int, array<string, mixed>>  $rows
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

    private function finishSizeElementName(string $finish): string
    {
        $name = 'sizes_'.preg_replace('/[\s-]+/', '_', strtolower(trim($finish)));
        $name = preg_replace('/[\s\/]+/', '_', $name) ?? $name;
        $name = preg_replace('/[^A-Za-z0-9._-]/', '', $name) ?? $name;

        return preg_replace('/_+/', '_', $name) ?? $name;
    }

    /**
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
     * @param  array<string, mixed>  $row
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

    private function compareSizes(string $left, string $right): int
    {
        $leftNumeric = $this->extractNumeric($left);
        $rightNumeric = $this->extractNumeric($right);

        return $leftNumeric === $rightNumeric
            ? strnatcasecmp($right, $left)
            : $rightNumeric <=> $leftNumeric;
    }

    private function extractNumeric(string $value): float
    {
        preg_match_all('/\d+(?:\.\d+)?/', $value, $matches);

        return $matches[0] === [] ? 0.0 : max(array_map('floatval', $matches[0]));
    }

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

    private function appendTextElement(DOMDocument $document, DOMElement $parent, string $name, string $value): DOMElement
    {
        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode($value));
        $parent->appendChild($element);

        return $element;
    }

    private function assertSupportedBrand(string $brand): void
    {
        if (! $this->supports($brand)) {
            throw new InvalidArgumentException("Unsupported XML brand [{$brand}].");
        }
    }
}
