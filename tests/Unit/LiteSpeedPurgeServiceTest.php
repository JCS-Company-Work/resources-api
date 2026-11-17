<?php

namespace Tests\Unit;

use App\Services\LiteSpeedPurgeService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LiteSpeedPurgeServiceTest extends TestCase
{
    private const URL = 'https://hub.example.test/purge';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cache_purge' => [
                'hub' => ['url' => self::URL, 'token' => 'secret-token'],
            ],
        ]);
    }

    public function test_it_posts_to_the_hub_with_the_purge_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::URL => Http::response(['success' => true, 'message' => 'Cache purged for Hub'])]);
        Log::shouldReceive('info')->once()->with('LiteSpeed cache purge requested.', ['site' => 'hub']);

        $result = (new LiteSpeedPurgeService)->purge();

        $this->assertSame(['hub' => ['success' => true, 'message' => 'Cache purged for Hub']], $result);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::URL
            && $request->hasHeader('X-Purge-Token', 'secret-token')
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_it_reports_when_configuration_is_missing(): void
    {
        Http::preventStrayRequests();
        Log::shouldReceive('error')->twice()->with('LiteSpeed cache purge failed.', [
            'site' => 'hub',
            'error' => 'Cache purge configuration is missing.',
        ]);

        foreach (['url', 'token'] as $missing) {
            config(['services.cache_purge.hub' => ['url' => self::URL, 'token' => 'secret-token']]);
            config(["services.cache_purge.hub.$missing" => null]);

            $this->assertSame(
                ['hub' => ['success' => false, 'message' => 'Cache purge configuration is missing.']],
                (new LiteSpeedPurgeService)->purge()
            );
        }

        Http::assertNothingSent();
    }

    public function test_it_logs_and_reports_when_the_hub_returns_an_error(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::URL => Http::response('Server error', 500)]);
        Log::shouldReceive('error')->once()->with('LiteSpeed cache purge failed.', [
            'site' => 'hub',
            'error' => 'Cache purge returned HTTP 500.',
        ]);

        $this->assertSame(
            ['hub' => ['success' => false, 'message' => 'Cache purge returned HTTP 500.']],
            (new LiteSpeedPurgeService)->purge()
        );
    }

    public function test_it_reports_when_the_hub_does_not_confirm_the_purge(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::URL => Http::response(['success' => false])]);
        Log::shouldReceive('info')->never();
        Log::shouldReceive('error')->once()->with('LiteSpeed cache purge failed.', [
            'site' => 'hub',
            'error' => 'The site did not confirm the cache purge request.',
        ]);

        $this->assertSame(
            ['hub' => ['success' => false, 'message' => 'The site did not confirm the cache purge request.']],
            (new LiteSpeedPurgeService)->purge()
        );
    }

    public function test_it_continues_to_other_sites_after_a_connection_failure(): void
    {
        config(['services.cache_purge' => [
            'hub' => ['url' => self::URL, 'token' => 'hub-token'],
            'tailor-made' => ['url' => 'https://tailor.example.test/purge', 'token' => 'tailor-token'],
        ]]);
        Http::preventStrayRequests();
        Http::fake([
            self::URL => Http::failedConnection(),
            'https://tailor.example.test/purge' => Http::response(['success' => true, 'message' => 'Cache purged for Tailor Made']),
        ]);

        $result = (new LiteSpeedPurgeService)->purge();

        $this->assertSame([
            'hub' => ['success' => false, 'message' => 'Cache purge request failed. Check the application log.'],
            'tailor-made' => ['success' => true, 'message' => 'Cache purged for Tailor Made'],
        ], $result);
        Http::assertSentCount(2);
    }

    public function test_it_accepts_a_successful_response_without_a_message(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::URL => Http::response(['success' => true])]);

        $this->assertSame(
            ['hub' => ['success' => true, 'message' => null]],
            (new LiteSpeedPurgeService)->purge()
        );
    }
}
