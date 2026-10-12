<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductApiTest extends TestCase
{
    public function test_it_returns_a_product_by_id(): void
    {

        // Access the product by its ID
        $response = $this->getJson('/api/products/3086');

        // Assert that the response contains the expected product data
        $response->assertOk()
            ->assertJson([
                'postID' => 3086,
                'colour' => 'Casablanca Gold Mix',
            ]);
    }

    public function test_it_returns_404_for_an_unknown_id(): void { 

        // Attempt to access a product that does not exist
        $response = $this->getJson('/api/products/DOES-NOT-EXIST-12345'); 
        
        // Assert that the response returns a 404 status and the expected error message
        $response->assertNotFound() ->assertJson([ 'message' => 'Product not found', ]); 
    }
}