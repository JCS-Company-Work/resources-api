<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataService;

class DataController extends Controller
{

    /**
     * Get all products
     *
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllProducts(DataService $dataService) {

        // Fetch all products from the data service
        $products = $dataService->getData('app/private/combined-data/', 'allbrands');

        return response()->json($products);
    }

    /**
     * Get all pending products
     *
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingProducts(DataService $dataService) {

        // Fetch all products from the data service
        $products = $dataService->getData('app/private/combined-data/', 'allpending');

        // Return data from pending products JSON
        return response()->json($products);
    }

    /**
     * Get a single product by its ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSingleProduct($id, DataService $dataService) {

        // Fetch all products from the data service
        $products = $dataService->getData('app/private/combined-data/', 'allbrands');
        
        // Return single product data based on the provided ID
        $product = collect($products)->firstWhere('postID', $id);

        // If no product is found, return a 404 response
        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json($product);
    }

    /**
     * Get all products within a specific range by its ID
     *
     * @param int $id
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSingleRange($id, DataService $dataService) {

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

    /**
     * Get Florim UK data
     *
     * @param DataService $dataService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFlorimData(DataService $dataService) {

        // Fetch Florim UK data from the data service
        $florimData = $dataService->getData('app/private/florimuk/', 'florimuk');

        return response()->json($florimData);
    }

}