<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleSheets\GoogleSheetsService;
use Illuminate\Http\JsonResponse;

class LiveDataController extends Controller
{
    public function __invoke(GoogleSheetsService $googleSheetsService): JsonResponse
    {
        return response()->json($googleSheetsService->getRows(), 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
