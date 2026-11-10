<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GetDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TMColoursController extends Controller
{
    /**
     * Get TM Colours filter panel data.
     *
     * @return JsonResponse
     */
    public function getTmColoursFilterPanel(Request $request, GetDataService $dataService)
    {

        // Fetch filter panel data
        $products = $dataService->getData('app/private/tailormade/', 'filter-panel');

        return response()->json($products);

    }

    /**
     * Get TM Colours main data.
     *
     * @return JsonResponse
     */
    public function getTmColoursData(Request $request, GetDataService $dataService)
    {

        // Fetch product data from the data service
        $products = $dataService->getData('app/private/tailormade/', 'tile-filter-data');

        return response()->json($products);

    }

    /**
     * Get TM Colours reference data.
     *
     * @return JsonResponse
     */
    public function getTmColoursRefData(Request $request, GetDataService $dataService)
    {

        // Fetch product reference data from the data service
        $products = $dataService->getData('app/private/tailormade/', 'tile-filter-ref-data');

        return response()->json($products);

    }
}
