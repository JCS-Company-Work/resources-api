<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\DataService;

class ProductFinderController extends Controller
{
    /**
     * Get Product Finder filter panel data.
     *
     * @param Request $request
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductFinderFilterPanel(Request $request, DataService $dataService) {

        // Fetch filter panel data
        $products = $dataService->getData('app/private/product-finder/porcelain-tiles/', 'filter-panel');

        return response()->json($products);

    }
    
    /**
     * Get Product Finder main data.
     *
     * @param Request $request
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductFinderData(Request $request, DataService $dataService) {

        // Fetch product data from the data service
        $products = $dataService->getData('app/private/product-finder/porcelain-tiles/', 'tile-filter-data');

        return response()->json($products);

    }
    
    /**
     * Get Product Finder reference data.
     *
     * @param Request $request
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductFinderRefData(Request $request, DataService $dataService) {

        // Fetch product reference data from the data service
        $products = $dataService->getData('app/private/product-finder/porcelain-tiles/', 'tile-filter-ref-data');

        return response()->json($products);

    }
}
