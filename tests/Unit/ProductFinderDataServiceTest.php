<?php

use App\Services\Resources\ProductFinderDataService;

function productFinderRow(array $overrides = []): array
{
    return array_merge([
        'range' => 'Marble Slabs',
        'rangeID' => '10',
        'colour' => 'Cinder',
        'factoryColour' => 'Cinder',
        'shade' => 'Grey',
        'finish' => 'Natural R10',
        'thickness' => '9.5',
        'size' => '600 x 1200 x 9.5',
        'rRating' => 'R10',
        'rectified' => 'Yes',
        'variation' => 'V2',
        'specialPiece' => '',
        'decor' => '',
        'factory' => 'Florim',
        'sampleAvailable' => 'TRUE',
        'picklistLocation' => 'A1',
        'collectionID' => '394',
        'ppCollection' => 'TM20',
        'postID' => '52712',
        'thumbnailUrl' => 'cinder-700x700.jpg',
        'appearsOn' => 'porcelaintiles',
        'pendingItem' => '',
        'note' => '',
        'productCode' => 'CINDER',
    ], $overrides);
}

test('it creates all three product finder resources for porcelain tiles', function () {
    $resources = (new ProductFinderDataService)->generate('porcelaintiles', [
        productFinderRow(),
        productFinderRow([
            'finish' => 'Polished',
            'thickness' => '12',
            'size' => '600 x 600 x 12',
        ]),
    ]);

    expect(array_keys($resources))->toBe([
        'tile-filter-data',
        'tile-filter-ref-data',
        'filter-panel',
    ])
        ->and($resources['tile-filter-data']['id-52712']['Finishes'])
        ->toHaveKeys(['Natural R10', 'Polished'])
        ->and($resources['tile-filter-data']['id-52712']['url'])
        ->toBe('https://www.porcelain-tiles.co.uk/wp-content/uploads/cinder-300x300.jpg')
        ->and($resources['tile-filter-ref-data'][0])
        ->toMatchArray([
            'finish' => 'natural',
            'thickness' => '9.5mm',
            'size' => '1200 x 600',
        ])
        ->and($resources['filter-panel']['Bookmatched']['filters'])
        ->toBe([[
            'label' => 'Bookmatched',
            'toggle-btn' => 'bookmatched',
        ]]);
});

test('it filters pending discontinued and other-brand rows', function () {
    $rows = [
        productFinderRow(),
        productFinderRow(['pendingItem' => 'Yes', 'postID' => '1']),
        productFinderRow(['note' => 'Discontinued by us', 'postID' => '2']),
        productFinderRow(['appearsOn' => 'platformplus', 'postID' => '3']),
        productFinderRow(['postID' => '', 'productCode' => '']),
    ];

    $resources = (new ProductFinderDataService)->generate('porcelaintiles', $rows);

    expect($resources['tile-filter-data'])->toHaveKey('id-52712')
        ->and($resources['tile-filter-data'])->not->toHaveKeys(['id-1', 'id-2', 'id-3']);
});

test('it retains unavailable samples in reference data but excludes them from main data', function () {
    $resources = (new ProductFinderDataService)->generate('porcelaintiles', [
        productFinderRow(),
        productFinderRow([
            'sampleAvailable' => 'FALSE',
            'postID' => '52713',
            'productCode' => 'UNAVAILABLE',
            'size' => '300 x 300 x 9.5',
        ]),
    ]);

    expect($resources['tile-filter-data'])->toHaveKey('id-52712')
        ->and($resources['tile-filter-data'])->not->toHaveKey('id-52713')
        ->and(array_column($resources['tile-filter-ref-data'], 'post_id'))
        ->toContain('52713');
});

test('it separates special pieces from regular sizes', function () {
    $resources = (new ProductFinderDataService)->generate('porcelaintiles', [
        productFinderRow(),
        productFinderRow([
            'specialPiece' => 'Mosaic (48x48mm)',
            'size' => '300 x 300 x 9.5',
        ]),
    ]);

    $finish = $resources['tile-filter-data']['id-52712']['Finishes']['Natural R10'];

    expect($finish['Size'])->toBe(['9-5mm' => ['600 x 1200 x 9.5']])
        ->and($finish['Special_Size'])->toBe([
            '9-5mm' => ['Mosaic (48x48mm)' => ['300 x 300 x 9.5']],
        ]);
});

test('it rejects an unsupported brand', function () {
    expect(fn () => (new ProductFinderDataService)->generate('unknown', []))
        ->toThrow(InvalidArgumentException::class);
});
