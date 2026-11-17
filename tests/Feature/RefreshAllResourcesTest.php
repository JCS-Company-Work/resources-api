<?php

use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\Resources\CombinedDataService;
use App\Services\Resources\FlorimDataService;
use App\Services\Resources\RefreshAllResourcesService;
use App\Services\Resources\ResourceSaveService;
use App\Services\Resources\SampleRequest\SampleRequestDataService;
use App\Services\Resources\SampleRequest\SampleRequestFormatterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('resource progress is flushed before the refresh completes', function () {
    $flushed = '';
    $flushedWhileRunning = false;
    $this->mock(RefreshAllResourcesService::class, function ($mock) use (&$flushed, &$flushedWhileRunning) {
        $mock->shouldReceive('refresh')->once()->andReturnUsing(
            function (callable $onResourceSaved) use (&$flushed, &$flushedWhileRunning): array {
                $onResourceSaved('combined-data/allbrands');
                $flushedWhileRunning = str_contains($flushed, '"resource":"combined-data\/allbrands"');

                return ['success' => true, 'resources' => [], 'cache_purge' => []];
            }
        );
    });

    $response = $this->withApiToken('resource_api')->putJson('/api/resources/refresh-all');
    ob_start(function (string $buffer) use (&$flushed): string {
        $flushed .= $buffer;

        return '';
    });
    try {
        $response->baseResponse->sendContent();
    } finally {
        ob_end_clean();
    }

    expect($flushedWhileRunning)->toBeTrue();
});

test('refresh all reports each site purge after saving resources', function () {
    $originalStoragePath = app()->storagePath();
    $testStoragePath = $originalStoragePath.'/framework/testing/refresh-all-'.Str::uuid();
    File::makeDirectory($testStoragePath, 0755, true);
    app()->useStoragePath($testStoragePath);

    try {
        config([
            'services.cache_purge' => [
                'hub' => ['url' => 'https://hub.example.test/purge', 'token' => 'hub-token'],
                'porcelain-tiles' => ['url' => 'https://porcelain.example.test/purge', 'token' => 'porcelain-token'],
                'tailor-made' => ['url' => 'https://tailor.example.test/purge', 'token' => 'tailor-token'],
            ],
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://hub.example.test/purge' => Http::response(['success' => true, 'message' => 'Cache purged for Hub']),
            'https://porcelain.example.test/purge' => Http::response('Server error', 500),
            'https://tailor.example.test/purge' => Http::response(['success' => true, 'message' => 'Cache purged for Tailor Made']),
        ]);
        $this->mock(GoogleSheetsService::class, function ($mock) {
            $mock->shouldReceive('getRows')->once()->withNoArgs()->andReturn([]);
            $mock->shouldReceive('getRows')->once()->with('sample-request')->andReturn([]);
        });

        $response = $this->withApiToken('resource_api')->putJson('/api/resources/refresh-all');
        $response->assertOk()->assertStreamed();
        $events = array_map(
            fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            explode("\n", trim($response->streamedContent()))
        );

        expect(end($events))->toMatchArray([
            'event' => 'completed',
            'success' => true,
            'cache_purge' => [
                'hub' => ['success' => true, 'message' => 'Cache purged for Hub'],
                'porcelain-tiles' => ['success' => false, 'message' => 'Cache purge returned HTTP 500.'],
                'tailor-made' => ['success' => true, 'message' => 'Cache purged for Tailor Made'],
            ],
        ]);
        expect($events)->toHaveCount(16)
            ->and($events[count($events) - 2])->toMatchArray([
                'event' => 'cache_purge',
                'cache_purge' => end($events)['cache_purge'],
            ]);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://hub.example.test/purge'
            && $request->hasHeader('X-Purge-Token', 'hub-token'));
        Http::assertSent(fn ($request) => $request->url() === 'https://tailor.example.test/purge'
            && $request->hasHeader('X-Purge-Token', 'tailor-token'));
    } finally {
        app()->useStoragePath($originalStoragePath);
        File::deleteDirectory($testStoragePath);
    }
});

test('a failed refresh reports the resource and partial purge results', function (string $failure) {
    $this->mock(GoogleSheetsService::class, function ($mock) {
        $mock->shouldReceive('getRows')->once()->withNoArgs()->andReturn([]);
        $mock->shouldReceive('getRows')->once()->with('sample-request')->andReturn([]);
    });
    $this->mock(CombinedDataService::class, function ($mock) use ($failure) {
        $mock->shouldReceive('createCombinedData')->once()->with('allbrands', [])->andReturn([]);
        $pending = $mock->shouldReceive('createCombinedData')->once()->with('allpending', []);
        if ($failure === 'generation') {
            $pending->andThrow(new RuntimeException('Generation failed'));
        } else {
            $pending->andReturn([]);
        }
    });
    $this->mock(ResourceSaveService::class, function ($mock) use ($failure) {
        $mock->shouldReceive('saveResource')->once()->with('allbrands.json', 'app/private/combined-data/', [], false);
        if ($failure === 'save') {
            $mock->shouldReceive('saveResource')->once()->with('allpending.json', 'app/private/combined-data/', [], false)
                ->andThrow(new RuntimeException('Save failed'));
        }
        $mock->shouldReceive('purge')->once()->with('refresh-all')
            ->andReturn(['hub' => ['success' => true, 'message' => 'Cache purged for Hub']]);
    });

    $response = $this->withApiToken('resource_api')->putJson('/api/resources/refresh-all');
    $response->assertOk()->assertStreamed();
    $events = array_map(
        fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        explode("\n", trim($response->streamedContent()))
    );

    expect($events)->toBe([
        ['event' => 'resource_updated', 'resource' => 'combined-data/allbrands'],
        ['event' => 'resource_failed', 'resource' => 'combined-data/allpending'],
        ['event' => 'cache_purge', 'cache_purge' => ['hub' => ['success' => true, 'message' => 'Cache purged for Hub']]],
        ['event' => 'failed', 'message' => $failure === 'generation' ? 'Generation failed' : 'Save failed'],
    ]);
})->with(['generation', 'save']);

test('it refreshes all generated resources from one sheets request', function () {
    $standardRows = [
        ['productCode' => 'ONYX', 'postID' => '1', 'appearsOn' => 'all', 'range' => 'Onyx Sense'],
        ['productCode' => 'ANIMA', 'postID' => '2', 'appearsOn' => 'all', 'range' => 'Anima'],
    ];
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
    $filterPanels = [];
    $updated = [];
    $this->mock(ResourceSaveService::class, function ($mock) use (&$filterPanels, &$updated) {
        $mock->shouldReceive('saveResource')->times(16)->andReturnUsing(
            function (string $filename, string $path, array $data, bool $purgeCache) use (&$filterPanels): void {
                expect($purgeCache)->toBeFalse();

                if ($filename === 'filter-panel.json' && str_starts_with($path, 'app/private/product-finder/')) {
                    $filterPanels[$path] = $data;
                }
            }
        );
        $mock->shouldReceive('purge')->once()->with('refresh-all')->andReturnUsing(
            function () use (&$updated): array {
                expect($updated)->toHaveCount(16);

                return [];
            }
        );
    });

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
        ->and($updated)->toContain('product-finder/tailormade/filter-panel')
        ->and(array_column($filterPanels['app/private/product-finder/porcelaintiles/']['Range']['filters'], 'label'))
        ->toBe(['Anima', 'Onyx Sense'])
        ->and(array_column($filterPanels['app/private/product-finder/tailormade/']['Range']['filters'], 'label'))
        ->toBe(['Anima', 'Onyx Sense']);
});

test('it purges once when a refresh fails after saving a resource', function () {
    $this->mock(GoogleSheetsService::class, function ($mock) {
        $mock->shouldReceive('getRows')->once()->withNoArgs()->andReturn([]);
        $mock->shouldReceive('getRows')->once()->with('sample-request')->andReturn([]);
    });
    $this->mock(CombinedDataService::class, function ($mock) {
        $mock->shouldReceive('createCombinedData')->once()->with('allbrands', [])->andReturn([]);
        $mock->shouldReceive('createCombinedData')->once()->with('allpending', [])->andThrow(new RuntimeException('Generation failed'));
    });
    $this->mock(ResourceSaveService::class, function ($mock) {
        $mock->shouldReceive('saveResource')->once()->with('allbrands.json', 'app/private/combined-data/', [], false);
        $mock->shouldReceive('purge')->once()->with('refresh-all');
    });

    expect(fn () => app(RefreshAllResourcesService::class)->refresh())
        ->toThrow(RuntimeException::class, 'Generation failed');

    $lock = Cache::lock('resources-refresh-all', 1800);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

test('it does not purge when a refresh fails before saving any resources', function () {
    $this->mock(GoogleSheetsService::class, function ($mock) {
        $mock->shouldReceive('getRows')->once()->withNoArgs()->andThrow(new RuntimeException('Sheets unavailable'));
    });
    $this->mock(ResourceSaveService::class, function ($mock) {
        $mock->shouldNotReceive('saveResource');
        $mock->shouldNotReceive('purge');
    });

    expect(fn () => app(RefreshAllResourcesService::class)->refresh())
        ->toThrow(RuntimeException::class, 'Sheets unavailable');
});

test('it requires the dedicated refresh token', function () {
    $this->putJson('/api/resources/refresh-all')
        ->assertUnauthorized();
});
