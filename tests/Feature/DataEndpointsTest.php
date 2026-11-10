<?php

namespace Tests\Feature;

use App\Services\GetDataService;
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
            'product finder filter panel' => ['/api/product-finder/filter-panel', 'app/private/product-finder/porcelaintiles/', 'filter-panel', 'product_finder_api'],
            'product finder data' => ['/api/product-finder/product-data', 'app/private/product-finder/porcelaintiles/', 'tile-filter-data', 'product_finder_api'],
            'product finder ref data' => ['/api/product-finder/product-ref-data', 'app/private/product-finder/porcelaintiles/', 'tile-filter-ref-data', 'product_finder_api'],
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

        $this->mock(GetDataService::class, function ($mock) use ($path, $file, $payload) {
            $mock->shouldReceive('getData')->once()->with($path, $file)->andReturn($payload);
        });

        $this->withApiToken($client)
            ->getJson($uri)
            ->assertOk()
            ->assertExactJson($payload);
    }

    public function test_pt_site_templates_excludes_hidden_fields_for_every_item(): void
    {
        $hidden = [
            'price', 'partnerPrice', 'florimukPrice', 'manCode', 'mesh', 'factoryCollection',
            'factoryRange', 'factoryColour', 'factorySurface', 'florimukItem', 'agreement',
        ];

        $items = [
            ['postID' => 1, 'productCode' => 'A1', ...array_fill_keys($hidden, 'secret')],
            ['postID' => 2, 'productCode' => 'B2', ...array_fill_keys($hidden, 'secret')],
        ];

        $this->mock(GetDataService::class, function ($mock) use ($items) {
            $mock->shouldReceive('getData')->once()->with('app/private/combined-data/', 'allbrands')->andReturn($items);
        });

        $this->withApiToken('combined_data_api')
            ->getJson('/api/ptsitetemplates')
            ->assertOk()
            ->assertExactJson([
                ['postID' => 1, 'productCode' => 'A1'],
                ['postID' => 2, 'productCode' => 'B2'],
            ]);
    }

    public function test_product_finder_all_combines_every_dataset(): void
    {
        $path = 'app/private/product-finder/porcelaintiles/';
        $datasets = [
            'filter-panel' => [['filter' => 'colour']],
            'tile-filter-data' => [['postID' => 1]],
            'tile-filter-ref-data' => [['ref' => 'A']],
        ];

        $this->mock(GetDataService::class, function ($mock) use ($path, $datasets) {
            foreach ($datasets as $file => $data) {
                $mock->shouldReceive('getData')->once()->with($path, $file)->andReturn($data);
            }
        });

        $this->withApiToken('product_finder_api')
            ->getJson('/api/product-finder/all')
            ->assertOk()
            ->assertExactJson([
                'filterPanel' => $datasets['filter-panel'],
                'productData' => $datasets['tile-filter-data'],
                'productRefData' => $datasets['tile-filter-ref-data'],
            ]);
    }

    public function test_product_finder_all_requires_the_product_finder_token(): void
    {
        $this->getJson('/api/product-finder/all')->assertUnauthorized();

        foreach (array_diff(array_keys(self::API_KEYS), ['product_finder_api']) as $otherClient) {
            $this->withApiToken($otherClient)
                ->getJson('/api/product-finder/all')
                ->assertUnauthorized();
        }
    }

    public function test_pt_site_templates_requires_a_token(): void
    {
        $this->getJson('/api/ptsitetemplates')->assertUnauthorized();
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
