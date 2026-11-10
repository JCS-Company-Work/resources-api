<?php

namespace Tests\Unit;

use App\Services\LiteSpeedPurgeService;
use App\Services\Resources\ResourceSaveService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ResourceSaveServiceTest extends TestCase
{
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
                $mock->shouldReceive('purgeHub')->once();
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
