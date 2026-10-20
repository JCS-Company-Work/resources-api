<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ResourceUploadController;
use App\Http\Middleware\VerifyApiToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResourceUploadAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Replace the real controller so no files are written.
        $this->mock(ResourceUploadController::class, function ($mock) {
            $mock->shouldReceive('update')
                ->andReturn(response()->json(['message' => 'Mock upload']));
        });
    }

    public function test_upload_rejects_a_missing_token(): void
    {
        $this->putJson('/api/resources/allbrands', ['test' => 'data'])
            ->assertUnauthorized();
    }

    public function test_upload_rejects_an_invalid_token(): void
    {
        $this->withHeader('X-API-Token', 'wrong-token')
            ->putJson('/api/resources/allbrands', ['test' => 'data'])
            ->assertUnauthorized();
    }

    public function test_upload_accepts_a_valid_token(): void
    {
        $this->withApiToken('resource_api')
            ->putJson('/api/resources/allbrands', ['test' => 'data'])
            ->assertOk()
            ->assertJson(['message' => 'Mock upload']);
    }

    public function test_every_configured_client_key_is_accepted(): void
    {
        foreach (array_keys(self::API_KEYS) as $client) {
            $this->withApiToken($client)
                ->putJson('/api/resources/allbrands', ['test' => 'data'])
                ->assertOk();
        }
    }

    public function test_an_empty_configured_key_never_authenticates(): void
    {
        config(['services.api_keys' => ['resource_api' => null, 'samples_api' => '']]);

        $this->withHeader('X-API-Token', 'anything')
            ->putJson('/api/resources/allbrands', ['test' => 'data'])
            ->assertUnauthorized();
    }

    public function test_every_api_route_requires_a_token(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/') && $route->uri() !== 'api/user');

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains(
                VerifyApiToken::class,
                $route->gatherMiddleware(),
                "Route [{$route->uri()}] is missing VerifyApiToken."
            );
        }
    }
}