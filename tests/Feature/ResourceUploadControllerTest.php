<?php

namespace Tests\Feature;

use App\Services\ResourceUpdateService;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ResourceUploadControllerTest extends TestCase
{
    /**
     * call() ignores withHeader(), so the token is sent as a server variable.
     *
     * @return array<string, string>
     */
    private function rawJsonServer(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_TOKEN' => self::API_KEYS['resource_api'],
        ];
    }

    public function test_it_returns_success_when_the_service_updates_the_resource(): void
    {
        $this->mock(ResourceUpdateService::class, function ($mock) {
            $mock->shouldReceive('update')->once()->with('allbrands', 'combined-data', '', '{"a":1}');
        });

        $this->withApiToken('resource_api')
            ->call('PUT', '/api/resources/combined-data/allbrands', [], [], [], $this->rawJsonServer(), '{"a":1}')
            ->assertOk()
            ->assertJson(['message' => 'Resource updated successfully.']);
    }

    public function test_it_returns_422_when_the_service_rejects_the_input(): void
    {
        $this->mock(ResourceUpdateService::class, function ($mock) {
            $mock->shouldReceive('update')->andThrow(new InvalidArgumentException('Invalid JSON supplied.'));
        });

        $this->withApiToken('resource_api')
            ->putJson('/api/resources/combined-data/allbrands', ['a' => 1])
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid JSON supplied.']);
    }

    public function test_it_returns_500_when_the_service_cannot_write(): void
    {
        $this->mock(ResourceUpdateService::class, function ($mock) {
            $mock->shouldReceive('update')->andThrow(new RuntimeException('disk full'));
        });

        $this->withApiToken('resource_api')
            ->putJson('/api/resources/combined-data/allbrands', ['a' => 1])
            ->assertStatus(500)
            ->assertJson(['message' => 'Could not update the resource.']);
    }

    public function test_it_returns_422_for_an_unknown_resource(): void
    {
        $this->withApiToken('resource_api')
            ->putJson('/api/resources/combined-data/not-allowed', ['a' => 1])
            ->assertStatus(422);
    }

    public function test_it_returns_422_for_a_non_json_body(): void
    {
        $this->withApiToken('resource_api')
            ->call('PUT', '/api/resources/combined-data/allbrands', [], [], [], $this->rawJsonServer(), '{invalid json')
            ->assertStatus(422);
    }

    public function test_it_passes_the_product_finder_brand_to_the_service(): void
    {
        $this->mock(ResourceUpdateService::class, function ($mock) {
            $mock->shouldReceive('update')->once()->with('filter-panel', 'product-finder', 'porcelaintiles', '{"a":1}');
        });

        $this->withApiToken('resource_api')
            ->call('PUT', '/api/resources/product-finder/porcelaintiles/filter-panel', [], [], [], $this->rawJsonServer(), '{"a":1}')
            ->assertOk()
            ->assertJson(['message' => 'Resource updated successfully.']);
    }
}
