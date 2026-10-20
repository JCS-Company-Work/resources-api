<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const API_KEYS = [
        'resource_api' => 'test-resource-token',
        'combined_data_api' => 'test-combined-token',
        'product_finder_api' => 'test-finder-token',
        'tm_colours_api' => 'test-tm-token',
        'samples_api' => 'test-samples-token',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.api_keys' => self::API_KEYS]);
    }

    /**
     * Send requests with a valid API token.
     */
    protected function withApiToken(string $client = 'combined_data_api'): static
    {
        return $this->withHeader('X-API-Token', self::API_KEYS[$client]);
    }
}
