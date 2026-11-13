<?php

namespace App\Services\Resources;

use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\Resources\SampleRequest\SampleRequestDataService;
use App\Services\Resources\SampleRequest\SampleRequestFormatterService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RefreshAllResourcesService
{
    public function __construct(
        private GoogleSheetsService $googleSheetsService,
        private CombinedDataService $combinedDataService,
        private SampleRequestDataService $sampleRequestDataService,
        private SampleRequestFormatterService $sampleRequestFormatterService,
        private ProductFinderDataService $productFinderDataService,
        private ResourceSaveService $resourceSaveService
    ) {}

    /**
     * Refresh every resource generated from the shared Google Sheets data.
     *
     * @return array{success: bool, resources: array<string, bool>}
     */
    public function refresh(?callable $onResourceSaved = null): array
    {
        $lock = Cache::lock('resources-refresh-all', 1800);

        if (! $lock->get()) {
            throw new RuntimeException('Another resource refresh is already running.');
        }

        try {
            $standardRows = $this->googleSheetsService->getRows();
            $sampleRequestRows = $this->googleSheetsService->getRows('sample-request');
            $stored = [];

            $this->saveResource($stored, 'combined-data/allbrands', 'allbrands.json', 'app/private/combined-data/', $this->combinedDataService->createCombinedData('allbrands', $standardRows), $onResourceSaved);
            $this->saveResource($stored, 'combined-data/allpending', 'allpending.json', 'app/private/combined-data/', $this->combinedDataService->createCombinedData('allpending', $standardRows), $onResourceSaved);

            foreach (['pt', 'pp', 'tm', 'domus', 'florimuk'] as $brand) {
                $formattedBrand = $this->sampleRequestFormatterService->formatBrand(
                    $brand,
                    $this->sampleRequestDataService->createSampleRequestData($brand, $sampleRequestRows)
                );

                if (in_array($brand, ['pp', 'tm'], true)) {
                    foreach ($formattedBrand as $filename => $data) {
                        $this->saveResource(
                            $stored,
                            "samples-tool/{$filename}",
                            "{$filename}.json",
                            'app/private/samples-tool/',
                            $data,
                            $onResourceSaved
                        );
                    }

                    continue;
                }

                $this->saveResource(
                    $stored,
                    "samples-tool/{$brand}",
                    "{$brand}.json",
                    'app/private/samples-tool/',
                    $formattedBrand,
                    $onResourceSaved
                );
            }

            foreach (['porcelaintiles', 'tailormade'] as $brand) {
                foreach ($this->productFinderDataService->generate($brand, $standardRows) as $filename => $data) {
                    $this->saveResource(
                        $stored,
                        "product-finder/{$brand}/{$filename}",
                        "{$filename}.json",
                        "app/private/product-finder/{$brand}/",
                        $data,
                        $onResourceSaved
                    );
                }
            }

            return [
                'success' => true,
                'resources' => $stored,
            ];
        } finally {
            $lock->release();
        }
    }

    private function saveResource(
        array &$stored,
        string $resource,
        string $filename,
        string $path,
        array $data,
        ?callable $onResourceSaved = null
    ): void {
        $this->resourceSaveService->saveResource($filename, $path, $data);
        $stored[$resource] = true;

        if ($onResourceSaved !== null) {
            $onResourceSaved($resource);
        }
    }
}
