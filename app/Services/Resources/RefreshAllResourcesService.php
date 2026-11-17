<?php

namespace App\Services\Resources;

use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\Resources\SampleRequest\SampleRequestDataService;
use App\Services\Resources\SampleRequest\SampleRequestFormatterService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class RefreshAllResourcesService
{
    /**
     * Construct the service with its dependencies.
     */
    public function __construct(
        private GoogleSheetsService $googleSheetsService,
        private CombinedDataService $combinedDataService,
        private FlorimDataService $florimDataService,
        private SampleRequestDataService $sampleRequestDataService,
        private SampleRequestFormatterService $sampleRequestFormatterService,
        private ProductFinderDataService $productFinderDataService,
        private ResourceSaveService $resourceSaveService
    ) {}

    /**
     * Refresh resources from shared sheet data and purge once after any saves.
     *
     * A failed refresh stops further updates, but still purges after partial saves.
     * Refresh success does not imply purge success; check each site's result.
     *
     * @param  (callable(string): void)|null  $onResourceSaved  Called after each saved resource.
     * @param  (callable(string): void)|null  $onResourceFailed  Called with the resource or brand being generated when it fails.
     * @param  (callable(array<string, array{success: bool, message: ?string}>): void)|null  $onCachePurged  Called with per-site results after purging.
     * @return array{success: bool, resources: array<string, bool>, cache_purge: array<string, array{success: bool, message: ?string}>}
     *
     * @throws RuntimeException If another refresh holds the lock.
     * @throws Throwable If generation, saving, or a callback fails.
     */
    public function refresh(
        ?callable $onResourceSaved = null,
        ?callable $onResourceFailed = null,
        ?callable $onCachePurged = null
    ): array {

        // Get a lock to prevent concurrent refreshes.
        $lock = Cache::lock('resources-refresh-all', 1800);

        // If we can't get the lock, another refresh is already running.
        if (! $lock->get()) {
            throw new RuntimeException('Another resource refresh is already running.');
        }

        // Initialize storage for saved resources and purge results.
        $stored = [];
        $currentResource = null;
        $purgeResults = [];

        try {

            // Fetch rows from the Google Sheets service.
            $standardRows = $this->googleSheetsService->getRows();

            // Fetch rows for the sample request sheet.
            $sampleRequestRows = $this->googleSheetsService->getRows('sample-request');

            // Refresh combined data, Florim, sample requests, and product finder resources.
            $this->refreshCombinedData($standardRows, $stored, $currentResource, $onResourceSaved);
            $this->refreshFlorim($standardRows, $stored, $currentResource, $onResourceSaved);
            $this->refreshSamples($sampleRequestRows, $stored, $currentResource, $onResourceSaved);
            $this->refreshProductFinder($standardRows, $stored, $currentResource, $onResourceSaved);

        } catch (Throwable $exception) {

            // Handle any exceptions that occur during the refresh process.
            if ($currentResource !== null && ! isset($stored[$currentResource]) && $onResourceFailed !== null) {
                $onResourceFailed($currentResource);
            }

            throw $exception;
        } finally {

            try {
                // Purge the cache if any resources were stored.
                if ($stored !== []) {
                    $purgeResults = $this->resourceSaveService->purge('refresh-all');

                    if ($onCachePurged !== null) {
                        $onCachePurged($purgeResults);
                    }
                }
            } finally {

                // Release the lock to allow other refresh processes to run.
                $lock->release();
            }
        }

        // Return the results of the refresh operation, including stored resources and cache purge results.
        return [
            'success' => true,
            'resources' => $stored,
            'cache_purge' => $purgeResults,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, bool>  $stored
     * @param  (callable(string): void)|null  $onResourceSaved
     */
    private function refreshCombinedData(array $rows, array &$stored, ?string &$currentResource, ?callable $onResourceSaved): void
    {
        // Refresh combined data resources for each specified name.
        foreach (['allbrands', 'allpending'] as $name) {

            // Set the current resource to the combined data resource for the given name.
            $currentResource = "combined-data/{$name}";

            // Save the combined data resource
            $this->saveResource(
                $stored,
                $currentResource,
                "{$name}.json",
                'app/private/combined-data/',
                $this->combinedDataService->createCombinedData($name, $rows),
                $onResourceSaved
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, bool>  $stored
     * @param  (callable(string): void)|null  $onResourceSaved
     */
    private function refreshFlorim(array $rows, array &$stored, ?string &$currentResource, ?callable $onResourceSaved): void
    {

        // Refresh the Florim resource.
        $currentResource = 'florimuk/florimuk';

        // Save the Florim resource using the generated data.
        $this->saveResource(
            $stored,
            $currentResource,
            'florimuk.json',
            'app/private/florimuk/',
            $this->florimDataService->generate($rows),
            $onResourceSaved
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, bool>  $stored
     * @param  (callable(string): void)|null  $onResourceSaved
     */
    private function refreshSamples(array $rows, array &$stored, ?string &$currentResource, ?callable $onResourceSaved): void
    {
        // Refresh sample request resources for each specified brand.
        foreach (['pt', 'pp', 'tm', 'domus', 'florimuk'] as $brand) {

            // Set the current resource to the sample request resource for the current brand.
            $currentResource = "samples-tool/{$brand}";

            // Format the sample request data for the current brand.
            $formattedBrand = $this->sampleRequestFormatterService->formatBrand(
                $brand,
                $this->sampleRequestDataService->createSampleRequestData($brand, $rows)
            );

            // Determine the files to save based on the brand and formatted data.
            $files = in_array($brand, ['pp', 'tm'], true)
                ? $formattedBrand
                : [$brand => $formattedBrand];

            // Save each determined file as a separate resource.
            foreach ($files as $filename => $data) {

                // Set the current resource to the specific file being saved.
                $currentResource = "samples-tool/{$filename}";

                // Save the current file as a resource.
                $this->saveResource(
                    $stored,
                    $currentResource,
                    "{$filename}.json",
                    'app/private/samples-tool/',
                    $data,
                    $onResourceSaved
                );
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, bool>  $stored
     * @param  (callable(string): void)|null  $onResourceSaved
     */
    private function refreshProductFinder(array $rows, array &$stored, ?string &$currentResource, ?callable $onResourceSaved): void
    {
        // Refresh product finder resources for each specified brand.
        foreach (['porcelaintiles', 'tailormade'] as $brand) {

            // Set the current resource to the product finder resource for the current brand.
            $currentResource = "product-finder/{$brand}";

            // Generate and save each product finder file for the current brand.
            foreach ($this->productFinderDataService->generate($brand, $rows) as $filename => $data) {

                // Update the current resource to include the specific product finder file.
                $currentResource = "product-finder/{$brand}/{$filename}";

                // Save the current product finder file as a resource.
                $this->saveResource(
                    $stored,
                    $currentResource,
                    "{$filename}.json",
                    "app/private/product-finder/{$brand}/",
                    $data,
                    $onResourceSaved
                );
            }
        }
    }

    /**
     * Save without an individual purge and notify the caller after recording success.
     *
     * @param  array<string, bool>  $stored
     * @param  array<mixed>  $data
     * @param  (callable(string): void)|null  $onResourceSaved
     */
    private function saveResource(
        array &$stored,
        string $resource,
        string $filename,
        string $path,
        array $data,
        ?callable $onResourceSaved = null
    ): void {

        // Save the resource using the resource save service.
        $this->resourceSaveService->saveResource($filename, $path, $data, false);

        // Mark the resource as stored.
        $stored[$resource] = true;

        // Notify the caller that the resource has been saved, if a callback is provided.
        if ($onResourceSaved !== null) {
            $onResourceSaved($resource);
        }
    }
}
