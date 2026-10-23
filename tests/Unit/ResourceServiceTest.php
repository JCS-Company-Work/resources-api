<?php

namespace Tests\Unit;

use App\Services\ResourceUpdateService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ResourceServiceTest extends TestCase
{
    public function test_get_allowed_filename_returns_correct_filename(): void
    {
        $service = new ResourceUpdateService;

        $this->assertSame('allbrands.json', $service->getAllowedFilename('allbrands', 'combined-data', ''));
        $this->assertSame('allpending.json', $service->getAllowedFilename('allpending', 'combined-data', ''));
        $this->assertSame('filter-panel.json', $service->getAllowedFilename('filter-panel', 'product-finder', 'porcelaintiles'));
        $this->assertSame('filter-panel.json', $service->getAllowedFilename('filter-panel', 'product-finder', 'tailormade'));
    }

    public function test_get_allowed_filename_throws_exception_for_invalid_resource(): void
    {
        $service = new ResourceUpdateService;

        $this->expectException(\InvalidArgumentException::class);
        $service->getAllowedFilename('invalid_resource', 'combined-data', '');
    }

    public function test_get_allowed_filename_rejects_an_invalid_product_finder_brand(): void
    {
        $service = new ResourceUpdateService;

        $this->expectException(\InvalidArgumentException::class);
        $service->getAllowedFilename('filter-panel', 'product-finder', 'unknown-brand');
    }

    public function test_update_rejects_invalid_json(): void
    {
        $service = new ResourceUpdateService;

        $this->expectException(\InvalidArgumentException::class);
        $service->update('allbrands', 'combined-data', '', '{invalid json');
    }

    public function test_update_rejects_json_string(): void
    {
        $service = new ResourceUpdateService;

        $this->expectException(\InvalidArgumentException::class);
        $service->update('allbrands', 'combined-data', '', '"hello"');
    }

    public function test_it_updates_a_resource_file_and_clears_its_cache(): void
    {
        $path = storage_path('app/private/combined-data/allbrands.json');
        $backup = file_exists($path) ? file_get_contents($path) : null;

        try {
            Cache::put('allbrands', ['old' => 'data']);

            $service = new ResourceUpdateService;
            $service->update('allbrands', 'combined-data', '', '{"test":"data"}');

            $this->assertSame(
                '{"test":"data"}',
                file_get_contents($path)
            );

            $this->assertFalse(Cache::has('allbrands'));
        } finally {
            $backup === null ? @unlink($path) : file_put_contents($path, $backup);
            Cache::forget('allbrands');
        }
    }

    public function test_it_updates_the_product_finder_file_for_the_selected_brand(): void
    {
        $paths = [
            'porcelaintiles' => storage_path('app/private/product-finder/porcelaintiles/filter-panel.json'),
            'tailormade' => storage_path('app/private/product-finder/tailormade/filter-panel.json'),
        ];
        $backups = [];

        foreach ($paths as $brand => $path) {
            $backups[$brand] = file_exists($path) ? file_get_contents($path) : null;
        }

        try {
            $service = new ResourceUpdateService;
            $service->update('filter-panel', 'product-finder', 'porcelaintiles', '{"brand":"porcelaintiles"}');
            $service->update('filter-panel', 'product-finder', 'tailormade', '{"brand":"tailormade"}');

            $this->assertSame('{"brand":"porcelaintiles"}', file_get_contents($paths['porcelaintiles']));
            $this->assertSame('{"brand":"tailormade"}', file_get_contents($paths['tailormade']));
        } finally {
            foreach ($paths as $brand => $path) {
                $backups[$brand] === null ? @unlink($path) : file_put_contents($path, $backups[$brand]);
            }
        }
    }
}
