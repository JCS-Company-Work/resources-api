<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataService;
use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\GoogleSheets\Resources\CombinedDataService;
use App\Services\GoogleSheets\Resources\ResourceSaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CombinedDataController extends Controller
{
    /**
     * Get all products
     *
     * @return JsonResponse
     */
    public function getAllProducts(DataService $dataService)
    {

        // Fetch all products from the data service
        $products = $dataService->getData('app/private/combined-data/', 'allbrands');

        return response()->json($products);
    }

    /**
     * Get all pending products
     *
     * @return JsonResponse
     */
    public function getPendingProducts(DataService $dataService)
    {

        // Fetch all products from the data service
        $products = $dataService->getData('app/private/combined-data/', 'allpending');

        // Return data from pending products JSON
        return response()->json($products);
    }

    /**
     * Get a single product by its ID
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function getSingleProduct($id, DataService $dataService)
    {

        // Fetch all products from the data service
        $products = $dataService->getData('app/private/combined-data/', 'allbrands');

        // Return single product data based on the provided ID
        $product = collect($products)->firstWhere('postID', $id);

        // If no product is found, return a 404 response
        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json($product);
    }

    /**
     * Get all products within a specific range by its ID
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function getSingleRange($id, DataService $dataService)
    {

        // Fetch all ranges from the data service
        $ranges = $dataService->getData('app/private/combined-data/', 'allbrands');

        // Return all products from range based on ID
        $range = collect($ranges)->where('rangeID', $id);

        // If no range is found, return a 404 response
        if ($range->isEmpty()) {
            return response()->json([
                'message' => 'Range not found',
            ], 404);
        }

        return response()->json($range);
    }

    public function getPtSiteTemplates(DataService $dataService)
    {

        // Fetch PT site templates from the data service
        $ptSiteTemplates = $dataService->getData('app/private/combined-data/', 'allbrands');

        $hiddenFields = [
            'price',
            'partnerPrice',
            'florimukPrice',
            'manCode',
            'mesh',
            'factoryCollection',
            'factoryRange',
            'factoryColour',
            'factorySurface',
            'florimukItem',
            'agreement',
        ];

        $templates = collect($ptSiteTemplates)
            ->map(fn (array $item): array => Arr::except($item, $hiddenFields))
            ->values();

        return response()->json($templates);
    }

    /**
     * Update combined data for a specific filename
     */
    public function update(
        string $filename,
        GoogleSheetsService $googleSheetsService,
        CombinedDataService $combinedDataService,
        ResourceSaveService $resourceSaveService
    ): JsonResponse {
        if (! in_array($filename, ['allbrands', 'allpending'], true)) {
            return response()->json([
                'message' => 'Unsupported combined data resource.',
            ], 422);
        }

        // Fetch combined data from the Google Sheets service
        $rows = $googleSheetsService->getRows();

        // Create final combined data using the combined data service
        $combinedData = $combinedDataService->createCombinedData($filename, $rows);

        // Save file in private storage using the ResourceSaveService
        $resourceSavePath = 'app/private/combined-data/';
        $resourceSaveService->saveResource($filename.'.json', $resourceSavePath, $combinedData);

        $filePath = 'combined-data/'.$filename.'.json';
        $isStored = Storage::disk('local')->exists($filePath);

        return response()->json(['success' => $isStored, 'filename' => $filename, 'filePath' => $filePath]);
    }
}
