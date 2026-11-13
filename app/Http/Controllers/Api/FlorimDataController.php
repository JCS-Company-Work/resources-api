<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GetDataService;
use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\Resources\FlorimDataService;
use App\Services\Resources\ResourceSaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class FlorimDataController extends Controller
{
    /**
     * Get Florim UK data
     *
     * @return JsonResponse
     */
    public function getFlorimData(GetDataService $dataService)
    {

        // Fetch Florim UK data from the data service
        $florimData = $dataService->getData('app/private/florimuk/', 'florimuk');

        return response()->json($florimData);
    }

    /**
     * Update Florim UK data from Google Sheets.
     */
    public function updateFlorimData(
        GoogleSheetsService $googleSheetsService,
        FlorimDataService $florimDataService,
        ResourceSaveService $resourceSaveService
    ): JsonResponse {
        $data = $florimDataService->generate($googleSheetsService->getRows());
        $filename = 'florimuk.json';
        $resourcePath = 'app/private/florimuk/';

        $resourceSaveService->saveResource($filename, $resourcePath, $data);

        return response()->json([
            'success' => Storage::disk('local')->exists('florimuk/'.$filename),
            'filename' => $filename,
            'filePath' => 'florimuk/'.$filename,
        ]);
    }
}
