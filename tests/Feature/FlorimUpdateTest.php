<?php

use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\LiteSpeedPurgeService;
use Illuminate\Support\Facades\Cache;

test('it formats and saves Florim UK data', function () {
    $path = storage_path('app/private/florimuk/florimuk.json');
    $backup = file_exists($path) ? file_get_contents($path) : null;

    try {
        $this->mock(GoogleSheetsService::class, function ($mock) {
            $mock->shouldReceive('getRows')->withNoArgs()->once()->andReturn([
                [
                    'productCode' => 'FLORIM-1',
                    'florimukItem' => 'TRUE',
                    'thickness' => '9mm',
                    'price' => '£1,234.00',
                    'partnerPrice' => '£987.00',
                    'sampleAvailable' => 'TRUE',
                    'dataEnd' => 'x',
                ],
                [
                    'productCode' => 'NOT-FLORIM',
                    'florimukItem' => 'FALSE',
                ],
            ]);
        });
        $this->mock(LiteSpeedPurgeService::class, fn ($mock) => $mock->shouldReceive('purgeHub'));
        Cache::forget('florimuk');

        $this->withApiToken('florimuk')
            ->putJson('/api/florimuk')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'filename' => 'florimuk.json',
                'filePath' => 'florimuk/florimuk.json',
            ]);

        $saved = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        expect($saved)->toHaveCount(1)
            ->and($saved[0])->toMatchArray([
                'productCode' => 'FLORIM-1',
                'thickness' => '9',
                'price' => '1234',
                'partnerPrice' => '987',
                'sampleAvailable' => true,
            ])
            ->and($saved[0])->not->toHaveKey('dataEnd');
    } finally {
        $backup === null ? @unlink($path) : file_put_contents($path, $backup);
        Cache::forget('florimuk');
    }
});

test('it rejects unauthenticated Florim updates', function () {
    $this->putJson('/api/florimuk')->assertUnauthorized();
});
