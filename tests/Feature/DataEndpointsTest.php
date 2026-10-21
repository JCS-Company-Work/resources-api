<?php

namespace Tests\Feature;

use App\Services\DataService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DataEndpointsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function endpoints(): array
    {
        return [
            'all products' => ['/api/products', 'app/private/combined-data/', 'allbrands', 'combined_data_api'],
            'pending products' => ['/api/pending', 'app/private/combined-data/', 'allpending', 'combined_data_api'],
            'florim uk' => ['/api/florimuk', 'app/private/florimuk/', 'florimuk', 'florimuk'],
            'sample request' => ['/api/sample-request/some-brand', 'app/private/samples-tool/', 'some-brand', 'samples_api'],
            'product finder filter panel' => ['/api/product-finder/filter-panel', 'app/private/product-finder/porcelain-tiles/', 'filter-panel', 'product_finder_api'],
            'product finder data' => ['/api/product-finder/product-data', 'app/private/product-finder/porcelain-tiles/', 'tile-filter-data', 'product_finder_api'],
            'product finder ref data' => ['/api/product-finder/product-ref-data', 'app/private/product-finder/porcelain-tiles/', 'tile-filter-ref-data', 'product_finder_api'],
            'tm colours filter panel' => ['/api/tm-colours/filter-panel', 'app/private/tailormade/', 'filter-panel', 'tm_colours_api'],
            'tm colours data' => ['/api/tm-colours/colour-data', 'app/private/tailormade/', 'tile-filter-data', 'tm_colours_api'],
            'tm colours ref data' => ['/api/tm-colours/colour-ref-data', 'app/private/tailormade/', 'tile-filter-ref-data', 'tm_colours_api'],
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
    public function test_endpoint_returns_the_data_for_its_file(string $uri, string $path, string $file, string $client): void
    {
        $payload = [['id' => 1, 'name' => 'Example']];

        $this->mock(DataService::class, function ($mock) use ($path, $file, $payload) {
            $mock->shouldReceive('getData')->once()->with($path, $file)->andReturn($payload);
        });

        $this->withApiToken($client)
            ->getJson($uri)
            ->assertOk()
            ->assertExactJson($payload);
    }

    #[DataProvider('endpoints')]
    public function test_endpoint_rejects_other_clients_keys(string $uri, string $path, string $file, string $client): void
    {
        foreach (array_diff(array_keys(self::API_KEYS), [$client]) as $otherClient) {
            $this->withApiToken($otherClient)
                ->getJson($uri)
                ->assertUnauthorized();
        }
    }

    #[DataProvider('endpointUris')]
    public function test_endpoint_rejects_requests_without_a_token(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized();
    }
}
