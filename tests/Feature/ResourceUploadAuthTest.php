<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ResourceUploadController;
use Tests\TestCase;

class ResourceUploadAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.resource_api.token' => 'test-secret-token',
        ]);

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
        $this->withHeader('X-API-Token', 'test-secret-token')
            ->putJson('/api/resources/allbrands', ['test' => 'data'])
            ->assertOk()
            ->assertJson(['message' => 'Mock upload']);
    }
}