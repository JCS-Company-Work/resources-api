<?php

namespace Tests\Feature;

use App\Services\DataService;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(DataService::class, function ($mock) {
            $mock->shouldReceive('getData')
                ->with('app/private/combined-data/', 'allbrands')
                ->andReturn([
                    ['postID' => 3086, 'rangeID' => 1785, 'colour' => 'Casablanca Gold Mix'],
                    ['postID' => 3087, 'rangeID' => 1785, 'colour' => 'Casablanca Grey'],
                ]);
        });
    }

    public function test_it_rejects_requests_without_a_token(): void
    {
        $this->getJson('/api/products/3086')->assertUnauthorized();
    }

    public function test_it_returns_a_product_by_id(): void
    {

        // Access the product by its ID
        $response = $this->withApiToken()->getJson('/api/products/3086');

        // Assert that the response contains the expected product data
        $response->assertOk()
            ->assertJson([
                'postID' => 3086,
                'colour' => 'Casablanca Gold Mix',
            ]);
    }

    public function test_it_returns_404_for_an_unknown_id(): void
    {

        // Attempt to access a product that does not exist
        $response = $this->withApiToken()->getJson('/api/products/DOES-NOT-EXIST-12345');

        // Assert that the response returns a 404 status and the expected error message
        $response->assertNotFound()->assertJson(['message' => 'Product not found']);
    }

    public function test_it_returns_all_products_in_a_range_by_id(): void
    {

        // Access the range by its ID
        $response = $this->withApiToken()->getJson('/api/ranges/1785');

        // Assert that the response contains the expected range data
        $response->assertOk()
            ->assertJsonStructure([
                '*' => [
                    'postID',
                    'rangeID',
                    'colour',
                ],
            ]);
    }

    public function test_it_returns_404_for_an_unknown_range_id(): void
    {

        // Attempt to access a range that does not exist
        $response = $this->withApiToken()->getJson('/api/ranges/DOES-NOT-EXIST-12345');

        // Assert that the response returns a 404 status and the expected error message
        $response->assertNotFound()
            ->assertJson([
                'message' => 'Range not found',
            ]);
    }
}