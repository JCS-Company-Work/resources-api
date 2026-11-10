<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GetDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductFinderController extends Controller
{
    /**
     * Get Product Finder filter panel data.
     *
     * @return JsonResponse
     */
    public function getProductFinderFilterPanel(Request $request, GetDataService $dataService)
    {

        // Fetch filter panel data
        $products = $dataService->getData('app/private/product-finder/porcelaintiles/', 'filter-panel');

        return response()->json($products);

    }

    /**
     * Get Product Finder main data.
     *
     * @return JsonResponse
     */
    public function getProductFinderData(Request $request, GetDataService $dataService)
    {

        // Fetch product data from the data service
        $products = $dataService->getData('app/private/product-finder/porcelaintiles/', 'tile-filter-data');

        return response()->json($products);

    }

    /**
     * Get Product Finder reference data.
     *
     * @return JsonResponse
     */
    public function getProductFinderRefData(Request $request, GetDataService $dataService)
    {

        // Fetch product reference data from the data service
        $products = $dataService->getData('app/private/product-finder/porcelaintiles/', 'tile-filter-ref-data');

        return response()->json($products);

    }

    /**
     * Get all Product Finder data including filter panel, main data, and reference data.
     *
     * @return JsonResponse
     */
    public function getAllProductFinderData(Request $request, GetDataService $dataService)
    {
        $path = 'app/private/product-finder/porcelaintiles/';

        return response()->json([
            'filterPanel' => $dataService->getData($path, 'filter-panel'),
            'productData' => $dataService->getData($path, 'tile-filter-data'),
            'productRefData' => $dataService->getData($path, 'tile-filter-ref-data'),
        ]);
    }
}
