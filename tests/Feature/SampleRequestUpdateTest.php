<?php

use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\LiteSpeedPurgeService;
use Illuminate\Support\Facades\Cache;

function sampleRequestSheetRow(array $overrides = []): array
{
    return array_merge([
        'range' => 'Marble Slabs',
        'colour' => 'Cinder',
        'postID' => '52712',
        'finish' => 'Polished',
        'thickness' => '12',
        'size' => '1600 x 3200 x 12',
        'specialPiece' => '',
        'decor' => '',
        'rRating' => '',
        'rectified' => 'Yes',
        'variation' => '',
        'factory' => 'Florim',
        'shade' => 'Brown',
        'collectionID' => '394',
        'thumbnailUrl' => 'cinder.jpg',
        'sampleAvailable' => 'TRUE',
        'pendingItem' => '',
        'appearsOn' => 'All',
        'attributes' => [],
        'picklistLocations' => ['Polished' => ['12mm' => 'A1']],
    ], $overrides);
}

test('it formats and saves the sample request file for a brand', function () {
    $path = storage_path('app/private/samples-tool/pt.json');
    $backup = file_exists($path) ? file_get_contents($path) : null;

    try {
        $this->mock(GoogleSheetsService::class, function ($mock) {
            $mock->shouldReceive('getRows')->with('sample-request')->once()->andReturn([
                sampleRequestSheetRow(),
                sampleRequestSheetRow(['colour' => 'Pending', 'pendingItem' => 'Yes']),
            ]);
        });
        $this->mock(LiteSpeedPurgeService::class, fn ($mock) => $mock->shouldReceive('purgeHub'));
        Cache::forget('pt');

        $this->withApiToken('samples_api')
            ->putJson('/api/sample-request/pt')
            ->assertOk()
            ->assertJson(['success' => true, 'filename' => 'pt.json']);

        $saved = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $savedColour = $saved['Marble Slabs']['Cinder-52712'];

        expect(array_keys($saved['Marble Slabs']))->toBe(['Cinder-52712'])
            ->and($savedColour)->not->toHaveKey('additional_data')
            ->and($savedColour['Finishes']['Polished']['Picklist_Location'])
            ->toBe(['Polished-12mm' => 'A1'])
            ->and($savedColour['attributes']['classesString'])
            ->toBe('cinder marble-slabs collection-394 thickness-12 format-320-x-160 brand-florim polished 52712 brown');
    } finally {
        $backup === null ? @unlink($path) : file_put_contents($path, $backup);
        Cache::forget('pt');
    }
});

test('it rejects an unknown brand without fetching the sheet', function () {
    $this->mock(GoogleSheetsService::class, fn ($mock) => $mock->shouldNotReceive('getRows'));

    $this->withApiToken('samples_api')
        ->putJson('/api/sample-request/not-a-brand')
        ->assertNotFound();
});
