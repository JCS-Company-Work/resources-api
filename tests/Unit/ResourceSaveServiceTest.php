<?php

namespace Tests\Unit;

use App\Services\LiteSpeedPurgeService;
use App\Services\Resources\ResourceSaveService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class ResourceSaveServiceTest extends TestCase
{
    public function test_it_logs_a_failed_deferred_purge_without_throwing(): void
    {
        $this->mock(LiteSpeedPurgeService::class, function ($mock) {
            $mock->shouldReceive('purge')->once()->andThrow(new RuntimeException('Hub is down'));
        });
        Log::shouldReceive('error')->once()->with('Cache purge failed after resource update.', [
            'resource' => 'refresh-all',
            'error' => 'Hub is down',
        ]);

        $this->assertSame(
            ['error' => ['success' => false, 'message' => 'Cache purge failed. Check the application log.']],
            app(ResourceSaveService::class)->purge('refresh-all')
        );
    }

    public function test_it_can_save_a_resource_without_purging_the_hub(): void
    {
        config(['cache.default' => 'array']);

        $path = storage_path('framework/testing/resource-save/deferred.json');
        $backup = file_exists($path) ? file_get_contents($path) : null;

        try {
            $this->mock(LiteSpeedPurgeService::class, function ($mock) {
                $mock->shouldNotReceive('purge');
            });
            Cache::put('deferred', ['old' => 'data']);

            app(ResourceSaveService::class)->saveResource('deferred.json', 'framework/testing/resource-save/', ['new' => 'data'], false);

            $this->assertSame('{"new":"data"}', file_get_contents($path));
            $this->assertFalse(Cache::has('deferred'));
        } finally {
            $backup === null ? @unlink($path) : file_put_contents($path, $backup);
            @unlink($path.'.tmp');
            @rmdir(dirname($path));
            Cache::forget('deferred');
        }
    }

    public function test_it_saves_generated_data_clears_its_cache_and_purges_the_hub(): void
    {
        config(['cache.default' => 'array']);

        $filename = 'generated.json';
        $resourcePath = 'framework/testing/resource-save/';
        $path = storage_path('framework/testing/resource-save/'.$filename);
        $temporaryPath = $path.'.tmp';
        $backup = file_exists($path) ? file_get_contents($path) : null;
        $data = [['productCode' => 'SKU-1']];

        try {
            $this->mock(LiteSpeedPurgeService::class, function ($mock) {
                $mock->shouldReceive('purge')->once();
            });

            Cache::put('generated', ['old' => 'data']);

            app(ResourceSaveService::class)->saveResource($filename, $resourcePath, $data);

            $this->assertSame(json_encode($data), file_get_contents($path));
            $this->assertFalse(Cache::has('generated'));
            $this->assertFileDoesNotExist($temporaryPath);
        } finally {
            $backup === null ? @unlink($path) : file_put_contents($path, $backup);
            @unlink($temporaryPath);
            @rmdir(dirname($path));
            Cache::forget('generated');
        }
    }
}
