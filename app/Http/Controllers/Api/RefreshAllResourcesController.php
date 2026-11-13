<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Resources\RefreshAllResourcesService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RefreshAllResourcesController extends Controller
{
    public function __invoke(RefreshAllResourcesService $refreshAllResourcesService): StreamedResponse
    {
        return response()->stream(function () use ($refreshAllResourcesService): void {
            try {
                $result = $refreshAllResourcesService->refresh(
                    function (string $resource): void {
                        if (connection_aborted() !== 0) {
                            throw new RuntimeException('Refresh cancelled because the client disconnected.');
                        }

                        echo json_encode([
                            'event' => 'resource_updated',
                            'resource' => $resource,
                        ])."\n";
                        flush();
                    }
                );

                echo json_encode([
                    'event' => 'completed',
                    ...$result,
                ])."\n";
                flush();
            } catch (RuntimeException $exception) {
                echo json_encode([
                    'event' => 'failed',
                    'message' => $exception->getMessage(),
                ])."\n";
                flush();
            } catch (Throwable $exception) {
                Log::error('Resource refresh failed.', [
                    'error' => $exception->getMessage(),
                ]);
                echo json_encode([
                    'event' => 'failed',
                    'message' => 'Resource refresh failed. Check the application log for details.',
                ])."\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
