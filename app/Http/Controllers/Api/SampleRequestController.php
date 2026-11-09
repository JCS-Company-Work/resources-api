<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SampleRequestController extends Controller
{
    /**
     * Get sample request data for a specific brand
     *
     * @param  string  $brand
     * @return JsonResponse
     */
    public function getSampleRequestData($brand, DataService $dataService)
    {

        // Fetch sample request data for the specified brand from the data service
        $sampleRequestData = $dataService->getData('app/private/samples-tool/', $brand);

        return response()->json($sampleRequestData);
    }
}
