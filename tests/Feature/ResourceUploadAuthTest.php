<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyApiToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResourceUploadAuthTest extends TestCase
{
    public function test_an_empty_configured_key_never_authenticates(): void
    {
        config(['services.api_keys' => ['resource_api' => null, 'samples_api' => '']]);

        $this->withHeader('X-API-Token', 'anything')
            ->putJson('/api/resources/refresh-all')
            ->assertUnauthorized();
    }

    public function test_removed_upload_routes_are_not_available(): void
    {
        $this->withApiToken('resource_api')
            ->putJson('/api/resources/samples-tool/pt', ['test' => 'data'])
            ->assertNotFound();

        $this->putJson('/api/resources/product-finder/porcelaintiles/filter-panel', ['test' => 'data'])
            ->assertNotFound();
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
