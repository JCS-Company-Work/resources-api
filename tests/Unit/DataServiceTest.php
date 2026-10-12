<?php

namespace Tests\Unit;

use App\Services\DataService;
use Tests\TestCase;

class DataServiceTest extends TestCase
{
    public function test_products_are_cached_between_calls(): void
    {

        // Use the array cache driver for testing purposes
        config(['cache.default' => 'array']);

        // Instantiate the data service
        $service = new DataService();

        // Call the getProducts method twice to test caching
        $firstResult = $service->getProducts();
        $secondResult = $service->getProducts();

        // Assert that the results from both calls are identical
        $this->assertSame($firstResult, $secondResult);
        
    }
}