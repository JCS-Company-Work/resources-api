<?php

namespace Tests\Unit;

use App\Services\LiteSpeedPurgeService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class LiteSpeedPurgeServiceTest extends TestCase
{
    private const URL = 'https://hub.example.test/purge';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hub_cache_purge.url' => self::URL,
            'services.hub_cache_purge.token' => 'secret-token',
        ]);
    }

    public function test_it_posts_to_the_hub_with_the_purge_token(): void
    {
        Http::fake([self::URL => Http::response(['success' => true])]);
        Log::shouldReceive('info')->once()->with('Hub LiteSpeed cache purge confirmed.');

        (new LiteSpeedPurgeService)->purgeHub();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::URL
            && $request->hasHeader('X-Purge-Token', 'secret-token')
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_it_throws_when_configuration_is_missing(): void
    {
        Http::fake();

        foreach (['url', 'token'] as $missing) {
            config(["services.hub_cache_purge.$missing" => null]);

            try {
                (new LiteSpeedPurgeService)->purgeHub();
                $this->fail('Expected a RuntimeException.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Hub cache purge configuration is missing.', $exception->getMessage());
            }

            $this->setUp();
        }

        Http::assertNothingSent();
    }

    public function test_it_logs_and_throws_when_the_hub_returns_an_error(): void
    {
        Http::fake([self::URL => Http::response('Server error', 500)]);
        Log::shouldReceive('error')->once()->with('Hub LiteSpeed purge failed.', [
            'status' => 500,
            'body' => 'Server error',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hub cache purge returned HTTP 500');

        (new LiteSpeedPurgeService)->purgeHub();
    }

    public function test_it_throws_when_the_hub_does_not_confirm_the_purge(): void
    {
        Http::fake([self::URL => Http::response(['success' => false])]);
        Log::shouldReceive('info')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hub did not confirm the cache purge.');

        (new LiteSpeedPurgeService)->purgeHub();
    }
}
