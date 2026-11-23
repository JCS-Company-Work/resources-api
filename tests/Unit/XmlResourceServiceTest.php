<?php

use App\Services\Resources\Data\XmlResourceService;

test('it generates the legacy XML schema for a brand and escapes product text', function () {
    $rows = [
        [
            'postID' => '15163',
            'appearsOn' => 'porcelaintiles-tailormade',
            'pendingItem' => 'No',
            'ppCollection' => 'TM6',
            'ppRange' => 'Marmi',
            'factory' => 'TM Exclusive',
            'factoryColour' => 'Agata Atena',
            'colour' => 'Gold & Agate',
            'shade' => 'Coloured Gem',
            'range' => 'Stone',
            'thickness' => '6',
            'rectified' => 'Yes',
            'finish' => 'Polished',
            'factorySurface' => 'Polished',
            'size' => '3000 x 1500 x 6',
            'thumbnailUrl' => 'Gem-Stone-Gold-Agate-700x700.jpg',
            'graphics' => 'https://images.example.test/small.jpg,512x1024,https://images.example.test/large.jpg,1280x2560',
            'graphicsFormat' => 'jpg',
        ],
        [
            'postID' => '15164',
            'appearsOn' => 'platformplus',
            'factoryColour' => 'Should be excluded',
        ],
    ];

    $xml = (new XmlResourceService)->generate('porcelain-tiles', $rows);
    $document = new DOMDocument;

    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->documentElement->tagName)->toBe('colours')
        ->and($document->getElementsByTagName('colour')->length)->toBe(1)
        ->and($xml)->toContain('Gold &amp; Agate')
        ->and($document->getElementsByTagName('colour')->item(0)->getAttribute('id'))->toBe('15163')
        ->and($document->getElementsByTagName('finish')->item(0)->getAttribute('ptfinish'))->toBe('Polished')
        ->and($document->getElementsByTagName('size')->item(0)->textContent)->toBe('300 x 150')
        ->and($document->getElementsByTagName('graphic')->item(0)->textContent)
        ->toContain('https://images.example.test/small.jpg');
});

test('it rejects unsupported XML brands', function () {
    expect(fn () => (new XmlResourceService)->generate('unknown', []))
        ->toThrow(InvalidArgumentException::class);
});

test('it includes products assigned to standalone XML brands', function (string $brand, string $appearsOn) {
    $xml = (new XmlResourceService)->generate($brand, [
        [
            'postID' => '100',
            'appearsOn' => $appearsOn,
            'factoryColour' => 'Factory Colour',
        ],
    ]);
    $document = new DOMDocument;

    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->getElementsByTagName('colour')->length)->toBe(1)
        ->and($document->getElementsByTagName('colour')->item(0)->getAttribute('id'))->toBe('100');
})->with([
    'tailor-made' => ['tailor-made', 'tailormade'],
    'porcelain-tiles' => ['porcelain-tiles', 'porcelaintiles'],
]);

test('it sanitizes finish names before creating XML size elements', function () {
    $xml = (new XmlResourceService)->generate('porcelain-tiles', [
        [
            'postID' => '101',
            'appearsOn' => 'porcelaintiles',
            'factoryColour' => 'Factory Colour',
            'factorySurface' => 'Natural & R10',
            'size' => '600 x 1200 x 9.5',
        ],
    ]);
    $document = new DOMDocument;

    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->getElementsByTagName('sizes_natural_r10')->length)->toBe(1);
});
