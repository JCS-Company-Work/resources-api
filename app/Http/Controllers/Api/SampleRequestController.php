<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GetDataService;
use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\Resources\ResourceSaveService;
use App\Services\Resources\SampleRequest\SampleRequestDataService;
use App\Services\Resources\SampleRequest\SampleRequestFormatterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SampleRequestController extends Controller
{
    /**
     * Get sample request data for a specific brand
     *
     * @param  string  $brand
     * @return JsonResponse
     */
    public function getSampleRequestData($brand, GetDataService $dataService)
    {

        // Fetch sample request data for the specified brand from the data service
        $sampleRequestData = $dataService->getData('app/private/samples-tool/', $brand);

        return response()->json($sampleRequestData);
    }

    /**
     * Update sample request data for a specific brand
     *
     * @param  string  $brand
     */
    public function updateSampleRequestData($brand, Request $request, SampleRequestDataService $dataService, SampleRequestFormatterService $formatter, GoogleSheetsService $googleSheetsService, ResourceSaveService $resourceSaveService): JsonResponse
    {

        if (! $formatter->supports($brand)) {
            return response()->json(['message' => 'Unknown brand'], 404);
        }

        // Fetch sample request rows (including attributes and locations) from Google Sheets
        $rows = $googleSheetsService->getRows('sample-request');

        // Select the tiles for this brand, then format them for the samples tool
        $brandTiles = $dataService->createSampleRequestData($brand, $rows);
        $sampleRequestData = $formatter->formatBrand($brand, $brandTiles);

        // Save the updated data using the resource save service
        $resourceSavePath = 'app/private/samples-tool/';
        $filename = $brand.'.json';

        // Save the updated sample request data using the resource save service
        $resourceSaveService->saveResource($filename, $resourceSavePath, $sampleRequestData);

        // Check if the file exists in storage
        $filePath = 'samples-tool/'.$filename;
        $isStored = Storage::disk('local')->exists($filePath);

        return response()->json(['success' => $isStored, 'filename' => $filename, 'filePath' => $filePath]);

    }
}
