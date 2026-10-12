<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use App\Services\ResourceUpdateService;

class ResourceUploadController extends Controller
{

    /**
     * Update the specified resource with the provided content.
     *
     * @param Request $request
     * @param string $resource
     * @param ResourceUpdateService $service
     * @return void
     */
    public function update(
        Request $request,
        string $resource,
        ResourceUpdateService $service
    ) {

        try {
            
            // Attempt to update the resource using the provided service.
            $service->update($resource, $request->getContent());

        } catch (InvalidArgumentException $e) {

            // If an invalid filename is provided, return a 422 response.
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);

        } catch (RuntimeException $e) {

            // If a runtime exception occurs, return a 500 response.
            return response()->json([
                'message' => 'Could not update the resource.',
            ], 500);
        }

        // If no exceptions were thrown, return a success response.
        return response()->json([
            'message' => 'Resource updated successfully.',
        ]);
    }
}