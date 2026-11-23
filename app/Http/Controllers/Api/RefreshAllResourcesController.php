<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Resources\Update\RefreshAllResourcesService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RefreshAllResourcesController extends Controller
{
    /**
     * Refresh all resources and stream the results as NDJSON.
     */
    public function __invoke(RefreshAllResourcesService $refreshAllResourcesService): StreamedResponse
    {
        return response()->stream(fn () => $this->streamRefresh($refreshAllResourcesService), 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Stream the refresh process and handle events.
     */
    private function streamRefresh(RefreshAllResourcesService $refreshAllResourcesService): void
    {
        try {

            // Start the refresh process and handle events.
            $result = $refreshAllResourcesService->refresh(
                fn (string $resource) => $this->sendResourceUpdated($resource),
                fn (string $resource) => $this->sendEvent('resource_failed', ['resource' => $resource]),
                fn (array $purgeResults) => $this->sendEvent('cache_purge', ['cache_purge' => $purgeResults])
            );

            // Send the completed event to the client.
            $this->sendEvent('completed', $result);

        } catch (RuntimeException $exception) {

            // Send the failed event to the client with the exception message.
            $this->sendEvent('failed', ['message' => $exception->getMessage()]);
        } catch (Throwable $exception) {

            // Log the error and send a generic failed event to the client.
            Log::error('Resource refresh failed.', [
                'error' => $exception->getMessage(),
            ]);

            // Send the failed event to the client with a standard message.
            $this->sendEvent('failed', ['message' => 'Resource refresh failed. Check the application log for details.']);
        }
    }

    /**
     * Send a resource updated event to the client as NDJSON.
     */
    private function sendResourceUpdated(string $resource): void
    {
        // Check if the client has disconnected before sending the event.
        if (connection_aborted() !== 0) {
            throw new RuntimeException('Refresh cancelled because the client disconnected.');
        }

        // Send the resource updated event to the client.
        $this->sendEvent('resource_updated', ['resource' => $resource]);
    }

    /**
     * Send an event to the client as NDJSON.
     */
    private function sendEvent(string $event, array $data): void
    {
        echo json_encode(['event' => $event, ...$data])."\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
