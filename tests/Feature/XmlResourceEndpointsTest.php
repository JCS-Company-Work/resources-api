<?php

use Illuminate\Support\Facades\Storage;

test('it returns the saved XML resource for a brand', function (string $brand, string $filename) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?><colours><colour id="1"/></colours>';
    Storage::fake('local');
    Storage::disk('local')->put('xml/'.$filename, $xml);

    $this->withApiToken('resource_api')
        ->get('/api/xml/'.$brand)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertContent($xml);
})->with([
    'platform-plus' => ['platform-plus', 'platform-plus.xml'],
    'tailor-made' => ['tailor-made', 'tailor-made.xml'],
    'porcelain-tiles' => ['porcelain-tiles', 'porcelain-tiles.xml'],
]);

test('it returns 404 for an unsupported XML brand', function () {
    $this->withApiToken('resource_api')
        ->get('/api/xml/not-a-brand')
        ->assertNotFound();
});

test('it requires the resource API token for XML resources', function () {
    $this->get('/api/xml/porcelain-tiles')->assertUnauthorized();
});
