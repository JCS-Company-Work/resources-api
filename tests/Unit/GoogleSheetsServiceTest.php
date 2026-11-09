<?php

namespace Tests\Unit;

use App\Services\GoogleSheets\GoogleSheetsService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class GoogleSheetsServiceTest extends TestCase
{
    public function test_it_maps_sheet_rows_to_keyed_rows_and_drops_blank_products(): void
    {
        $service = $this->serviceWithoutGoogleClient();
        $keys = ['productCode', 'sampleAvailable', 'dataEnd'];
        $rows = [
            ['Product Code', 'Sample Available', 'Data End'],
            ['SKU-1', 'true', ''],
            ['', '', ''],
        ];

        $combinedRows = $service->createCombinedData($keys, $rows);

        $this->assertSame([
            [
                'productCode' => 'SKU-1',
                'sampleAvailable' => 'true',
            ],
        ], $combinedRows);
    }

    public function test_it_formats_sheet_headers_as_camel_case_keys(): void
    {
        $service = $this->serviceWithoutGoogleClient();

        $this->assertSame(
            ['productCode', 'sampleAvailable', 'factoryRange'],
            $service->formatKeyNames(['Product Code', 'Sample-Available', 'Factory / Range'])
        );
    }

    private function serviceWithoutGoogleClient(): GoogleSheetsService
    {
        /** @var GoogleSheetsService $service */
        $service = (new ReflectionClass(GoogleSheetsService::class))->newInstanceWithoutConstructor();

        return $service;
    }
}
