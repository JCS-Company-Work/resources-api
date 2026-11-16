<?php

use App\Services\GoogleSheets\GoogleSheetsService;

test('it fetches fresh processed sheet data on every request', function () {
    $firstRows = [['productCode' => 'FIRST', 'range' => 'Onyx Sense']];
    $secondRows = [['productCode' => 'SECOND', 'range' => 'Anima']];

    $this->mock(GoogleSheetsService::class, function ($mock) use ($firstRows, $secondRows) {
        $mock->shouldReceive('getRows')->twice()->withNoArgs()->andReturn($firstRows, $secondRows);
    });

    $this->withApiToken('live_data_api')->getJson('/api/live-data')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson($firstRows);

    $this->withApiToken('live_data_api')->getJson('/api/live-data')
        ->assertOk()
        ->assertExactJson($secondRows);
});

test('it rejects missing and other client tokens without fetching sheet data', function () {
    $this->mock(GoogleSheetsService::class, fn ($mock) => $mock->shouldNotReceive('getRows'));

    $this->getJson('/api/live-data')->assertUnauthorized();

    foreach (array_diff(array_keys(config('services.api_keys')), ['live_data_api']) as $client) {
        $this->withApiToken($client)->getJson('/api/live-data')->assertUnauthorized();
    }

    $this->withHeader('X-API-Token', 'invalid-token')->getJson('/api/live-data')->assertUnauthorized();
});

test('it rejects requests when the live data token is not configured', function () {
    $this->mock(GoogleSheetsService::class, fn ($mock) => $mock->shouldNotReceive('getRows'));
    config(['services.api_keys.live_data_api' => null]);

    $this->withApiToken('live_data_api')->getJson('/api/live-data')->assertUnauthorized();
});

test('it returns an error when the sheet fetch fails', function () {
    $this->mock(GoogleSheetsService::class, function ($mock) {
        $mock->shouldReceive('getRows')->once()->withNoArgs()->andThrow(new RuntimeException('Sheet unavailable'));
    });

    $this->withApiToken('live_data_api')->getJson('/api/live-data')->assertInternalServerError();
});
