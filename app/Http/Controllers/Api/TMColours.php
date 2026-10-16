<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\DataService;

class TMColoursController extends Controller
{
    /**
     * Get TM Colours filter panel data.
     *
     * @param Request $request
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTmColoursFilterPanel(Request $request, DataService $dataService) {

        // Fetch filter panel data
        $products = $dataService->getData('app/private/tailor/', 'filter-panel');

        return response()->json($products);

    }
    /**
     * Get TM Colours main data.
     *
     * @param Request $request
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTmColoursData(Request $request, DataService $dataService) {

        // Fetch product data from the data service
        $products = $dataService->getData('app/private/product-finder/porcelain-tiles/', 'tile-filter-data');

        return response()->json($products);

    }
    /**
     * Get TM Colours reference data.
     *
     * @param Request $request
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTmColoursRefData(Request $request, DataService $dataService) {

        // Fetch product reference data from the data service
        $products = $dataService->getData('app/private/product-finder/porcelain-tiles/', 'tile-filter-ref-data');

        return response()->json($products);

    }
}
