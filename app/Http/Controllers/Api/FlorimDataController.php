<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataService;
use Illuminate\Http\JsonResponse;

class FlorimDataController extends Controller
{
    /**
     * Get Florim UK data
     *
     * @return JsonResponse
     */
    public function getFlorimData(DataService $dataService)
    {

        // Fetch Florim UK data from the data service
        $florimData = $dataService->getData('app/private/florimuk/', 'florimuk');

        return response()->json($florimData);
    }
}
