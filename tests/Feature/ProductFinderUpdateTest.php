<?php

use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\LiteSpeedPurgeService;
use Illuminate\Support\Facades\Storage;

function productFinderUpdateRow(array $overrides = []): array
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

test('it generates and saves all product finder resources', function () {
    $files = [
        storage_path('app/private/product-finder/porcelaintiles/filter-panel.json'),
        storage_path('app/private/product-finder/porcelaintiles/tile-filter-data.json'),
        storage_path('app/private/product-finder/porcelaintiles/tile-filter-ref-data.json'),
    ];
    $backups = array_map(fn (string $file): ?string => file_exists($file) ? file_get_contents($file) : null, $files);

    try {
        $this->mock(GoogleSheetsService::class, function ($mock) {
            $mock->shouldReceive('getRows')->once()->andReturn([productFinderUpdateRow()]);
        });
        $this->mock(LiteSpeedPurgeService::class, fn ($mock) => $mock->shouldReceive('purgeHub')->times(3));

        $response = $this->withApiToken('product_finder_api')
            ->putJson('/api/product-finder/porcelaintiles');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'brand' => 'porcelaintiles',
                'resources' => [
                    'tile-filter-data' => true,
                    'tile-filter-ref-data' => true,
                    'filter-panel' => true,
                ],
            ]);

        expect(Storage::disk('local')->exists('product-finder/porcelaintiles/filter-panel.json'))->toBeTrue()
            ->and(json_decode(file_get_contents($files[1]), true, 512, JSON_THROW_ON_ERROR))
            ->toHaveKey('id-52712');
    } finally {
        foreach ($files as $index => $file) {
            $backups[$index] === null ? @unlink($file) : file_put_contents($file, $backups[$index]);
        }
    }
});

test('it rejects an unsupported product finder brand without fetching sheets', function () {
    $this->mock(GoogleSheetsService::class, fn ($mock) => $mock->shouldNotReceive('getRows'));

    $this->withApiToken('product_finder_api')
        ->putJson('/api/product-finder/unknown')
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Unsupported product finder brand [unknown].',
        ]);
});
