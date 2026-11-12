<?php

use App\Services\Resources\SampleRequest\SampleRequestFormatterService;

function ptTile(array $overrides = []): array
{
    return array_merge([
        'range' => 'Marble Slabs',
        'colour' => 'Cinder',
        'postID' => '52712',
        'finish' => 'Natural R10',
        'thickness' => '9.5',
        'size' => '600 x 600 x 9.5',
        'specialPiece' => '',
        'decor' => '',
        'rRating' => 'R10',
        'rectified' => 'Yes',
        'variation' => 'V2',
        'factory' => 'Florim',
        'shade' => 'Brown',
        'collectionID' => '394',
        'thumbnailUrl' => 'cinder.jpg',
        'sampleAvailable' => 'TRUE',
    ], $overrides);
}

test('it groups pt tiles by range and colour with finishes keyed by hyphenated finish', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('pt', [
        ptTile(),
        ptTile(['size' => '300 x 600 x 9.5']),
        ptTile(['finish' => 'Polished', 'thickness' => '12', 'size' => '600 x 600 x 12']),
    ]);

    $colour = $result['Marble Slabs']['Cinder-52712'];

    expect($colour)->toMatchArray([
        'Post_ID' => '52712',
        'Colour' => 'Cinder',
        'Factory' => 'Florim',
        'Shade' => 'Brown',
        'Collection_ID' => '394',
        'Range' => 'Marble Slabs',
        'url' => 'https://www.porcelain-tiles.co.uk/wp-content/uploads/cinder.jpg',
    ])
        ->and(array_keys($colour['Finishes']))->toBe(['Natural-R10', 'Polished'])
        ->and($colour['Finishes']['Natural-R10'])->toMatchArray([
            'Size' => ['9-5mm' => ['600 x 600 x 9.5', '300 x 600 x 9.5']],
            'Sample_Options_Standard' => ['9-5mm' => ['Natural-R10 (9.5mm)']],
            'Thickness' => ['9-5mm' => '9.5'],
            'R_Rating' => 'R10',
            'Rectified' => 'Yes',
            'Variation' => 'V2',
        ]);
});

test('it skips tiles where a sample is not available', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('pt', [
        ptTile(['sampleAvailable' => 'FALSE']),
    ]);

    expect($result)->toBe([]);
});

test('it separates special pieces and collapses bookmatch blocks into one entry', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('pt', [
        ptTile(['thickness' => '8', 'specialPiece' => 'Mosaic (47x47mm)', 'size' => '300 x 300 x 8']),
        ptTile(['thickness' => '8', 'decor' => 'Bookmatch Block A', 'size' => '1620 x 3200 x 8']),
        ptTile(['thickness' => '8', 'decor' => 'Bookmatch Block B', 'size' => '1620 x 3200 x 8']),
    ]);

    $finish = $result['Marble Slabs']['Cinder-52712']['Finishes']['Natural-R10'];

    expect($finish['Size'])->toBe(['8mm' => ['300 x 300 x 8']])
        ->and($finish['Special_Size'])->toBe(['8mm' => [
            'Mosaic (47x47mm)' => ['300 x 300 x 8'],
            'Bookmatched' => '1620 x 3200 x 8',
        ]])
        ->and($finish['Sample_Options_Special'])->toBe(['8mm' => [
            'Mosaic (47x47mm)' => ['Natural-R10 (8mm) - Mosaic (47x47mm)'],
            'Bookmatched' => ['Natural-R10 (8mm) - Bookmatched'],
        ]]);
});

test('it returns nothing for an unknown brand', function () {
    expect((new SampleRequestFormatterService)->formatBrand('unknown', [ptTile()]))->toBe([]);
});

test('it builds the filter attributes for a colour', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('pt', [
        ptTile(['finish' => 'Polished', 'thickness' => '12', 'size' => '1600 x 3200 x 12']),
        ptTile(['finish' => 'Natural R10', 'thickness' => '9.5', 'size' => '600 x 1200 x 9.5']),
    ]);

    expect($result['Marble Slabs']['Cinder-52712']['attributes'])->toBe([
        'rangeClasses' => 'marble-slabs',
        'collectionClasses' => 'collection-394',
        'thicknessClasses' => 'thickness-12 thickness-9-5',
        'sizesCount' => 2,
        'sizeClasses' => 'format-320-x-160 format-120-x-60',
        'factoryClasses' => 'brand-florim',
        'finishClasses' => 'polished natural-r10',
        'classesString' => 'cinder marble-slabs collection-394 thickness-12 thickness-9-5 format-320-x-160 format-120-x-60 brand-florim polished natural-r10 52712 brown',
    ]);
});

test('it adds picklist locations only to finishes that have a match', function () {
    $locations = ['Natural-R10' => ['9-5mm' => 'T2-4-R']];

    $result = (new SampleRequestFormatterService)->formatBrand('pt', [
        ptTile(['picklistLocations' => $locations]),
        ptTile(['picklistLocations' => $locations, 'finish' => 'Polished', 'thickness' => '12']),
    ]);

    $finishes = $result['Marble Slabs']['Cinder-52712']['Finishes'];

    expect($finishes['Natural-R10']['Picklist_Location'])->toBe(['Natural-R10-9-5mm' => 'T2-4-R'])
        ->and($finishes['Polished'])->not->toHaveKey('Picklist_Location');
});

function ppTile(array $overrides = []): array
{
    return ptTile(array_merge([
        'factoryColour' => 'Yamuna',
        'ppRange' => 'Marmi',
        'ppCollection' => 'TM20',
        'factorySurface' => 'Honed',
        'finish' => 'Natural',
        'factory' => 'Florim Stone',
        'postID' => '55923',
        'thickness' => '20',
        'size' => '1600 x 3200 x 20',
        'shade' => 'Grey',
        'picklistLocations' => ['Natural' => ['20mm' => 'A1']],
    ], $overrides));
}

test('it formats platform plus tiles by factory with a combined key', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('pp', [
        ppTile(),
        ppTile(['factory' => 'Ergon', 'factoryColour' => 'Zed']),
    ]);

    expect(array_keys($result))->toBe(['florim', 'level', 'pp'])
        ->and(array_keys($result['pp']))->toBe(['Yamuna-Marmi-55923', 'Zed-Marmi-55923'])
        ->and($result['level'])->toHaveKey('Zed-Marmi-55923');

    $colour = $result['florim']['Yamuna-Marmi-55923'];

    expect($colour['Factory'])->toBe('Florim')
        ->and($colour['PP_Range'])->toBe('Marmi')
        ->and($colour['url'])->toBe('https://www.tailormade.uk/wp-content/uploads/cinder.jpg')
        ->and($colour['Finishes']['Silk'])->toMatchArray([
            'Finish' => 'Silk',
            'Factory_Surface' => 'Silk',
            'Factory' => 'Florim',
            'Size' => ['20mm' => ['1600 x 3200 x 20']],
            'Sample_Options_Standard' => ['20mm' => ['Silk (20mm)']],
            'Sample_Options_Special' => [],
        ])
        ->and($colour['attributes'])->toBe([
            'thicknessClasses' => 'thickness-20',
            'sizesCount' => 1,
            'sizeClasses' => 'format-320-x-160',
            'showOnTM' => true,
            'factoryClasses' => 'brand-florim',
            'finishClasses' => 'silk',
            'classesString' => 'yamuna thickness-20 format-320-x-160 brand-florim silk marmi tm20 55923 grey',
        ]);
});

test('it bridges the pt finish to the platform plus finish for picklist locations', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('pp', [ppTile()]);

    expect($result['pp']['Yamuna-Marmi-55923']['Finishes']['Silk']['Picklist_Location'])
        ->toBe(['Silk-20mm' => 'A1']);
});

test('it formats tailormade tiles like platform plus under the tm key', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('tm', [ppTile()]);

    expect(array_keys($result))->toBe(['florim', 'tm'])
        ->and($result['tm'])->toHaveKey('Yamuna-Marmi-55923');
});

test('it formats florimuk tiles flat, using the factory range and no sizes count', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('florimuk', [
        ppTile(['factoryRange' => 'Rinascenza', 'factory' => 'Florim Stone']),
    ]);

    $colour = $result['Yamuna-Rinascenza-55923'];

    expect($colour['Factory'])->toBe('Florim Stone')
        ->and($colour['Factory_Range'])->toBe('Rinascenza')
        ->and($colour)->not->toHaveKey('PP_Range')
        ->and($colour['attributes'])->not->toHaveKey('sizesCount')
        ->and($colour['attributes']['classesString'])
        ->toBe('yamuna thickness-20 format-320-x-160 brand-florim-stone silk 55923 grey');
});

test('it formats domus tiles flat, taking the picklist location from the tile', function () {
    $result = (new SampleRequestFormatterService)->formatBrand('domus', [
        ppTile(['picklistLocation' => 'D1', 'picklistLocations' => [], 'attributes' => ['altcolours' => 'x', 'faces' => '']]),
        ppTile([
            'factorySurface' => 'Matte',
            'finish' => 'Matte',
            'picklistLocation' => '',
            'picklistLocations' => [],
        ]),
    ]);

    $colour = $result['Yamuna-Marmi-55923'];

    expect($colour['Finishes']['Silk']['Picklist_Location'])->toBe(['Silk-20mm' => 'D1'])
        ->and($colour['Finishes']['Silk'])->not->toHaveKey('Finish')
        ->and(array_keys($colour['Finishes']['Matte']))->toBe([
            'Sample_Options_Special',
            'Sample_Options_Standard',
            'Size',
            'Thickness',
            'Factory_Surface',
            'Factory',
            'R_Rating',
            'Variation',
            'Picklist_Location',
        ])
        ->and($colour['Finishes']['Matte']['Picklist_Location'])->toBe('')
        ->and($colour['additional_data'])->toBe(['altcolours' => 'x', 'faces' => ''])
        ->and($colour['PP_Range'])->toBe('Marmi')
        ->and($colour['attributes']['classesString'])
        ->toBe('yamuna thickness-20 format-320-x-160 brand-florim-stone silk matte 55923 grey');
});

test('it only adds additional data for brands that use it', function () {
    $tile = ppTile(['attributes' => ['altcolours' => 'x']]);
    $formatter = new SampleRequestFormatterService;

    expect($formatter->formatBrand('pp', [$tile])['pp']['Yamuna-Marmi-55923'])->not->toHaveKey('additional_data')
        ->and($formatter->formatBrand('tm', [$tile])['tm']['Yamuna-Marmi-55923']['additional_data'])->toBe(['altcolours' => 'x']);
});
