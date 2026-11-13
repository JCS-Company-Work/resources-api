<?php

use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\Resources\CombinedDataService;
use App\Services\Resources\FlorimDataService;
use App\Services\Resources\ProductFinderDataService;
use App\Services\Resources\RefreshAllResourcesService;
use App\Services\Resources\ResourceSaveService;
use App\Services\Resources\SampleRequest\SampleRequestDataService;
use App\Services\Resources\SampleRequest\SampleRequestFormatterService;

test('it refreshes all generated resources from one sheets request', function () {
    $standardRows = [['productCode' => 'CINDER']];
    $sampleRequestRows = [['productCode' => 'CINDER', 'attributes' => [], 'picklistLocations' => []]];

    $this->mock(GoogleSheetsService::class, function ($mock) use ($standardRows, $sampleRequestRows) {
        $mock->shouldReceive('getRows')->once()->withNoArgs()->andReturn($standardRows);
        $mock->shouldReceive('getRows')->once()->with('sample-request')->andReturn($sampleRequestRows);
    });
    $this->mock(CombinedDataService::class, function ($mock) use ($standardRows) {
        $mock->shouldReceive('createCombinedData')->twice()->withArgs(
            fn (string $filename, array $receivedRows): bool => in_array($filename, ['allbrands', 'allpending'], true)
                && $receivedRows === $standardRows
        )->andReturn([]);
    });
    $this->mock(FlorimDataService::class, function ($mock) use ($standardRows) {
        $mock->shouldReceive('generate')->once()->with($standardRows)->andReturn([]);
    });
    $this->mock(SampleRequestDataService::class, function ($mock) use ($sampleRequestRows) {
        $mock->shouldReceive('createSampleRequestData')->times(5)->withArgs(
            fn (string $brand, array $receivedRows): bool => in_array($brand, ['pt', 'pp', 'tm', 'domus', 'florimuk'], true)
                && $receivedRows === $sampleRequestRows
        )->andReturn([]);
    });
    $this->mock(SampleRequestFormatterService::class, function ($mock) {
        $mock->shouldReceive('formatBrand')->times(5)->andReturnUsing(
            function (string $brand): array {
                if ($brand === 'pp') {
                    return ['pp' => [], 'emilgroup' => []];
                }

                if ($brand === 'tm') {
                    return ['tm' => [], 'tm exclusive' => []];
                }

                return [];
            }
        );
    });
    $this->mock(ProductFinderDataService::class, function ($mock) {
        $mock->shouldReceive('generate')->twice()->andReturn([
            'tile-filter-data' => [],
            'tile-filter-ref-data' => [],
            'filter-panel' => [],
        ]);
    });
    $this->mock(ResourceSaveService::class, function ($mock) {
        $mock->shouldReceive('saveResource')->times(16);
    });

    $updated = [];
    $result = app(RefreshAllResourcesService::class)->refresh(
        function (string $resource) use (&$updated): void {
            $updated[] = $resource;
        }
    );

    expect($result['success'])->toBeTrue()
        ->and($updated)->toHaveCount(16)
        ->and($updated)->toContain('florimuk/florimuk')
        ->and($updated)->toContain('samples-tool/emilgroup')
        ->and($updated)->toContain('samples-tool/tm exclusive')
        ->and($updated)->toContain('product-finder/tailormade/filter-panel');
});

test('it requires the dedicated refresh token', function () {
    $this->putJson('/api/resources/refresh-all')
        ->assertUnauthorized();
});
