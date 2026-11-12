<?php

namespace Tests\Feature;

use App\Services\GoogleSheets\GoogleSheetsService;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchGetValuesResponse;
use Google\Service\Sheets\ValueRange;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class GoogleSheetsAttachRangeDataTest extends TestCase
{
    public function test_it_attaches_matching_range_rows_to_each_product(): void
    {
        $response = new BatchGetValuesResponse;
        $response->setValueRanges([
            $this->valueRange([['Product_Code', 'Post_ID'], ['SKU-1', '100'], ['SKU-2', '200']]),
            $this->valueRange([['ID', 'Attributes', 'Data_End'], ['100', '{"category":"stone","tag":"grey tm6"}', 'End']]),
            $this->valueRange([['Colour', 'Post_ID', 'Thickness', 'Finish', 'Location'], ['Cinder', '100', '9.5', 'Natural R10', 'T2-4-R'], ['Cinder', '100', '12', 'Polished', 'T3-1-L']]),
        ]);

        $values = Mockery::mock();
        $values->shouldReceive('batchGet')->once()->andReturn($response);
        $values->shouldNotReceive('get');
        $sheets = (new ReflectionClass(Sheets::class))->newInstanceWithoutConstructor();
        $sheets->spreadsheets_values = $values;

        $service = (new ReflectionClass(GoogleSheetsService::class))->newInstanceWithoutConstructor();
        (new ReflectionClass($service))->getProperty('service')->setValue($service, $sheets);

        $result = $service->getRows('sample-request');

        $this->assertSame(['attributes' => 'stone grey tm6'], $result[0]['attributes']);
        $this->assertSame([
            'Natural-R10' => ['9-5mm' => 'T2-4-R'],
            'Polished' => ['12mm' => 'T3-1-L'],
        ], $result[0]['picklistLocations']);
        $this->assertSame([], $result[1]['attributes']);
        $this->assertSame([], $result[1]['picklistLocations']);
    }

    public function test_it_fetches_only_the_main_range_for_other_callers(): void
    {
        $response = new BatchGetValuesResponse;
        $response->setValueRanges([$this->valueRange([['Product_Code'], ['SKU-1']])]);

        $values = Mockery::mock();
        $values->shouldReceive('batchGet')->once()->andReturn($response);
        $sheets = (new ReflectionClass(Sheets::class))->newInstanceWithoutConstructor();
        $sheets->spreadsheets_values = $values;

        $service = (new ReflectionClass(GoogleSheetsService::class))->newInstanceWithoutConstructor();
        (new ReflectionClass($service))->getProperty('service')->setValue($service, $sheets);

        $this->assertSame([['productCode' => 'SKU-1']], $service->getRows());
    }

    private function valueRange(array $values): ValueRange
    {
        $range = new ValueRange;
        $range->setValues($values);

        return $range;
    }
}
