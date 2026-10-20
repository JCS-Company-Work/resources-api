<?php

namespace Tests\Feature;

use App\Services\DataService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DataEndpointsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function endpoints(): array
    {
        return [
            'all products' => ['/api/products', 'app/private/combined-data/', 'allbrands'],
            'pending products' => ['/api/pending', 'app/private/combined-data/', 'allpending'],
            'florim uk' => ['/api/florimuk', 'app/private/florimuk/', 'florimuk'],
            'sample request' => ['/api/sample-request/some-brand', 'app/private/samples-tool/', 'some-brand'],
            'product finder filter panel' => ['/api/product-finder/filter-panel', 'app/private/product-finder/porcelain-tiles/', 'filter-panel'],
            'product finder data' => ['/api/product-finder/product-data', 'app/private/product-finder/porcelain-tiles/', 'tile-filter-data'],
            'product finder ref data' => ['/api/product-finder/product-ref-data', 'app/private/product-finder/porcelain-tiles/', 'tile-filter-ref-data'],
            'tm colours filter panel' => ['/api/tm-colours/filter-panel', 'app/private/tailormade/', 'filter-panel'],
            'tm colours data' => ['/api/tm-colours/colour-data', 'app/private/tailormade/', 'tile-filter-data'],
            'tm colours ref data' => ['/api/tm-colours/colour-ref-data', 'app/private/tailormade/', 'tile-filter-ref-data'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function endpointUris(): array
    {
        return array_map(fn (array $endpoint) => [$endpoint[0]], self::endpoints());
    }

    #[DataProvider('endpoints')]
    public function test_endpoint_returns_the_data_for_its_file(string $uri, string $path, string $file): void
    {
        $payload = [['id' => 1, 'name' => 'Example']];

        $this->mock(DataService::class, function ($mock) use ($path, $file, $payload) {
            $mock->shouldReceive('getData')->once()->with($path, $file)->andReturn($payload);
        });

        $this->withApiToken()
            ->getJson($uri)
            ->assertOk()
            ->assertExactJson($payload);
    }

    #[DataProvider('endpointUris')]
    public function test_endpoint_rejects_requests_without_a_token(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized();
    }
}
