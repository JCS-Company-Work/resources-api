<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Resources\Data\XmlResourceService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class XmlResourceController extends Controller
{
    public function show(string $brand, XmlResourceService $xmlResourceService): Response
    {
        if (! $xmlResourceService->supports($brand)) {
            abort(404, 'XML resource not found.');
        }

        $path = $xmlResourceService->resourcePath($brand);
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404, 'XML resource not found.');
        }

        $xml = $disk->get($path);

        if (! is_string($xml)) {
            throw new RuntimeException("Could not read XML resource [{$path}].");
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
