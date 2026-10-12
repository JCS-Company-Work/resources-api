<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataService;
class DataController extends Controller
{

    /**
     * Get a single product by its ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSingleProduct($id, DataService $dataService) {

        // Fetch all products from the data service
        $products = $dataService->getProducts();
        
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
}
