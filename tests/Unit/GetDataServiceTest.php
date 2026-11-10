<?php

namespace Tests\Unit;

use App\Services\GetDataService;
use Tests\TestCase;

class GetDataServiceTest extends TestCase
{
    public function test_data_is_cached_between_calls(): void
    {
        config(['cache.default' => 'array']);

        $path = storage_path('framework/testing/data-service-test.json');
        file_put_contents($path, '{"version":1}');

        try {
            $service = new GetDataService;

            $firstResult = $service->getData('framework/testing/', 'data-service-test');

            // Changing the file must not affect the cached result.
            file_put_contents($path, '{"version":2}');
            $secondResult = $service->getData('framework/testing/', 'data-service-test');

            $this->assertSame(['version' => 1], $firstResult);
            $this->assertSame($firstResult, $secondResult);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_throws_when_the_data_file_is_missing(): void
    {
        config(['cache.default' => 'array']);

        $this->expectException(\ErrorException::class);

        (new GetDataService)->getData('framework/testing/', 'does-not-exist');
    }

    public function test_it_throws_when_the_data_file_contains_invalid_json(): void
    {
        config(['cache.default' => 'array']);

        $path = storage_path('framework/testing/data-service-invalid.json');
        file_put_contents($path, '{invalid');

        try {
            $this->expectException(\JsonException::class);

            (new GetDataService)->getData('framework/testing/', 'data-service-invalid');
        } finally {
            @unlink($path);
        }
    }
}
