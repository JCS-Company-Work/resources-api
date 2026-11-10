<?php

namespace Tests\Feature;

use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\LiteSpeedPurgeService;
use App\Services\Resources\CombinedDataService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CombinedDataGenerationTest extends TestCase
{
    public function test_it_generates_and_saves_all_brands_from_google_sheets(): void
    {
        $path = storage_path('app/private/combined-data/allbrands.json');
        $backup = file_exists($path) ? file_get_contents($path) : null;
        $sheetRows = [[
            'productCode' => 'SKU-1',
            'pendingItem' => 'No',
            'factory' => 'Ergon',
            'thickness' => '12 mm',
            'price' => '£1,234.00',
            'partnerPrice' => '£567.00',
            'sampleAvailable' => 'TRUE',
        ]];
        $expectedData = [[
            'productCode' => 'SKU-1',
            'pendingItem' => 'No',
            'factory' => 'Level',
            'thickness' => '12',
            'price' => '1234',
            'partnerPrice' => '567',
            'sampleAvailable' => true,
        ]];

        try {
            $this->mock(GoogleSheetsService::class, function ($mock) use ($sheetRows) {
                $mock->shouldReceive('getRows')->once()->andReturn($sheetRows);
            });
            $this->mock(LiteSpeedPurgeService::class, function ($mock) {
                $mock->shouldReceive('purgeHub')->once();
            });

            Cache::put('allbrands', ['old' => 'data']);

            $this->withApiToken('combined_data_api')
                ->putJson('/api/resources/combined-data/allbrands')
                ->assertOk()
                ->assertJson([
                    'success' => true,
                    'filename' => 'allbrands',
                    'filePath' => 'combined-data/allbrands.json',
                ]);

            $this->assertSame(
                $expectedData,
                json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
            );
            $this->assertFalse(Cache::has('allbrands'));
        } finally {
            $backup === null ? @unlink($path) : file_put_contents($path, $backup);
            Cache::forget('allbrands');
        }
    }

    public function test_it_rejects_an_unsupported_resource_filename(): void
    {
        $path = storage_path('app/private/combined-data/unsupported.json');

        try {
            $this->mock(GoogleSheetsService::class, function ($mock) {
                $mock->shouldNotReceive('getRows');
            });
            $this->mock(CombinedDataService::class, function ($mock) {
                $mock->shouldNotReceive('createCombinedData');
            });

            $this->withApiToken('combined_data_api')
                ->putJson('/api/resources/combined-data/unsupported')
                ->assertStatus(422)
                ->assertJson(['message' => 'Unsupported combined data resource.']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_requests_with_a_token_for_another_api_client(): void
    {
        $this->withApiToken('resource_api')
            ->putJson('/api/resources/combined-data/allbrands')
            ->assertUnauthorized();
    }
}
