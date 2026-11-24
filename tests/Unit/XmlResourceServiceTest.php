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

test('it builds legacy XML attributes from sheet fields rather than saved JSON', function () {
    $xml = (new XmlResourceService)->generate('porcelain-tiles', [
        [
            'postID' => '41526',
            'appearsOn' => 'porcelaintiles-tailormade',
            'factoryColour' => 'Example',
            'ppCollection' => 'TM12',
            'ppRange' => 'Bookmatch',
            'shade' => 'Grey',
            'range' => 'Marble',
            'thickness' => '12',
            'rectified' => 'Yes',
            'attributes' => ['attributes' => '{"category":"tm12tm20","tag":"other","thickness":"20mm"}'],
        ],
        [
            'postID' => '41527',
            'appearsOn' => 'porcelaintiles-tailormade',
            'factoryColour' => 'Another',
            'ppCollection' => 'TMGrip',
            'ppRange' => 'Cemento',
            'attributes' => ['attributes' => '{"rectified":"yes"}'],
        ],
    ]);
    $document = new DOMDocument;
    $document->loadXML($xml);
    $colours = [];
    foreach ($document->getElementsByTagName('colour') as $colour) {
        $colours[$colour->getAttribute('id')] = $colour;
    }

    expect($colours['41526']->getAttribute('category'))->toBe('bookmatch tm12')
        ->and($colours['41526']->getAttribute('tag'))->toBe('grey marble')
        ->and($colours['41526']->getAttribute('thickness'))->toBe('12mm')
        ->and($colours['41526']->getAttribute('rectified'))->toBe('yes')
        ->and($colours['41527']->getAttribute('category'))->toBe('tmgrip cemento')
        ->and($colours['41527']->getAttribute('tag'))->toBe('')
        ->and($colours['41527']->getAttribute('thickness'))->toBe('mm')
        ->and($colours['41527']->hasAttribute('rectified'))->toBeFalse();
});

test('it chooses attributes using legacy collection and range ordering', function () {
    $xml = (new XmlResourceService)->generate('porcelain-tiles', [
        ['postID' => '1', 'productCode' => '1', 'factoryColour' => 'First', 'appearsOn' => 'all', 'ppCollection' => 'TM6', 'ppRange' => 'Pietre'],
        ['postID' => '2', 'productCode' => '2', 'factoryColour' => 'Shared', 'appearsOn' => 'all', 'ppCollection' => 'TMGrip', 'ppRange' => 'Cemento', 'thickness' => '20'],
        ['postID' => '2', 'productCode' => '3', 'factoryColour' => 'Shared', 'appearsOn' => 'all', 'ppCollection' => 'TM6', 'ppRange' => 'Cemento', 'thickness' => '6'],
        ['postID' => '3', 'productCode' => '4', 'factoryColour' => 'Range', 'appearsOn' => 'all', 'ppCollection' => 'TM6', 'ppRange' => 'Cemento', 'thickness' => '20'],
        ['postID' => '3', 'productCode' => '5', 'factoryColour' => 'Range', 'appearsOn' => 'all', 'ppCollection' => 'TM6', 'ppRange' => 'Marmi', 'thickness' => '6'],
    ]);
    $document = new DOMDocument;
    $document->loadXML($xml);
    $colours = [];
    foreach ($document->getElementsByTagName('colour') as $colour) {
        $colours[$colour->getAttribute('id')] = $colour;
    }

    expect($colours['2']->getAttribute('category'))->toBe('tm6 cemento')
        ->and($colours['2']->getAttribute('thickness'))->toBe('6mm')
        ->and($colours['3']->getAttribute('category'))->toBe('tm6 marmi')
        ->and($colours['3']->getAttribute('thickness'))->toBe('6mm');
});

test('it orders colours by legacy collection and range order', function () {
    $rows = [
        ['postID' => '1', 'productCode' => '1', 'factoryColour' => 'Pietre', 'appearsOn' => 'all', 'ppCollection' => 'TM6', 'ppRange' => 'Pietre'],
        ['postID' => '2', 'productCode' => '2', 'factoryColour' => 'Zebra', 'appearsOn' => 'all', 'ppCollection' => 'TM12', 'ppRange' => 'Stone'],
        ['postID' => '3', 'productCode' => '3', 'factoryColour' => 'Alpha', 'appearsOn' => 'all', 'ppCollection' => 'TM12', 'ppRange' => 'Marble'],
        ['postID' => '4', 'productCode' => '4', 'factoryColour' => 'Shared', 'appearsOn' => 'all', 'ppCollection' => 'TMGrip', 'ppRange' => 'Stone'],
        ['postID' => '4', 'productCode' => '5', 'factoryColour' => 'Shared', 'appearsOn' => 'all', 'ppCollection' => 'TM6', 'ppRange' => 'Marmi'],
        ['postID' => '5', 'productCode' => '6', 'factoryColour' => 'Alabaster', 'appearsOn' => 'all', 'ppCollection' => 'TM6', 'ppRange' => 'Marmi'],
        ['postID' => '6', 'productCode' => '7', 'factoryColour' => 'Apple', 'appearsOn' => 'all', 'ppCollection' => 'TM12', 'ppRange' => 'Stone'],
        ['postID' => '7', 'productCode' => '8', 'factoryColour' => 'Grip', 'appearsOn' => 'all', 'ppCollection' => 'TMGrip', 'ppRange' => 'Stone'],
    ];

    $document = new DOMDocument;
    $document->loadXML((new XmlResourceService)->generate('tailor-made', $rows));
    $ids = [];
    foreach ($document->getElementsByTagName('colour') as $colour) {
        $ids[] = $colour->getAttribute('id');
    }

    expect($ids)->toBe(['5', '4', '1', '6', '2', '3', '7']);
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
]);

test('porcelain tiles XML excludes standalone rows while retaining shared rows', function () {
    $xml = (new XmlResourceService)->generate('porcelain-tiles', [
        ['postID' => '101', 'factoryColour' => 'Standalone', 'appearsOn' => 'porcelaintiles'],
        ['postID' => '102', 'factoryColour' => 'Shared', 'appearsOn' => 'porcelaintiles-tailormade'],
        ['postID' => '103', 'factoryColour' => 'All Brands', 'appearsOn' => 'all'],
        ['postID' => '102', 'factoryColour' => 'Shared', 'appearsOn' => 'porcelaintiles', 'finish' => 'Excluded Finish'],
    ]);
    $document = new DOMDocument;
    $document->loadXML($xml);

    $ids = [];
    foreach ($document->getElementsByTagName('colour') as $colour) {
        $ids[] = $colour->getAttribute('id');
    }

    sort($ids);
    expect($ids)->toBe(['102', '103'])
        ->and($xml)->not->toContain('Excluded Finish');
});

test('it sanitizes finish names before creating XML size elements', function () {
    $xml = (new XmlResourceService)->generate('porcelain-tiles', [
        [
            'postID' => '101',
            'appearsOn' => 'porcelaintiles-tailormade',
            'factoryColour' => 'Factory Colour',
            'factorySurface' => 'Natural & R10',
            'size' => '600 x 1200 x 9.5',
        ],
    ]);
    $document = new DOMDocument;

    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->getElementsByTagName('sizes_natural_r10')->length)->toBe(1);
});
