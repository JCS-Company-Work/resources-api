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

    public function test_upload_rejects_other_clients_keys(): void
    {
        foreach (array_diff(array_keys(self::API_KEYS), ['resource_api']) as $client) {
            $this->withApiToken($client)
                ->putJson('/api/resources/allbrands', ['test' => 'data'])
                ->assertUnauthorized();
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
            $hasToken = collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, VerifyApiToken::class.':'));

            $this->assertTrue($hasToken, "Route [{$route->uri()}] is missing VerifyApiToken.");
        }
    }
}
