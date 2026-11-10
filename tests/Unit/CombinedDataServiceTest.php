<?php

namespace Tests\Unit;

use App\Services\Resources\CombinedDataService;
use PHPUnit\Framework\TestCase;

class CombinedDataServiceTest extends TestCase
{
    public function test_it_normalizes_non_pending_products_for_all_brands(): void
    {
        $rows = [
            [
                'productCode' => 'SKU-1',
                'pendingItem' => 'No',
                'factory' => 'Ergon',
                'thickness' => '12 mm',
                'price' => '£1,234.00',
                'partnerPrice' => '£567.00',
                'sampleAvailable' => 'TRUE',
            ],
            [
                'productCode' => 'SKU-2',
                'pendingItem' => 'Yes',
                'factory' => 'Other',
                'thickness' => '6 mm',
                'price' => '10',
                'partnerPrice' => '5',
                'sampleAvailable' => 'false',
            ],
        ];

        $products = (new CombinedDataService)->createCombinedData('allbrands', $rows);

        $this->assertSame([
            [
                'productCode' => 'SKU-1',
                'pendingItem' => 'No',
                'factory' => 'Level',
                'thickness' => '12',
                'price' => '1234',
                'partnerPrice' => '567',
                'sampleAvailable' => true,
            ],
        ], $products);
    }

    public function test_it_normalizes_only_pending_products_for_all_pending(): void
    {
        $rows = [
            [
                'productCode' => 'SKU-1',
                'pendingItem' => 'No',
                'factory' => 'Other',
                'thickness' => '6 mm',
                'price' => '10',
                'partnerPrice' => '5',
                'sampleAvailable' => 'false',
            ],
            [
                'productCode' => 'SKU-2',
                'pendingItem' => ' YES ',
                'factory' => 'Provenza',
                'thickness' => '8 mm',
                'price' => '£2,000.00',
                'partnerPrice' => '£800.00',
                'sampleAvailable' => 'false',
            ],
        ];

        $products = (new CombinedDataService)->createCombinedData('allpending', $rows);

        $this->assertSame([
            [
                'productCode' => 'SKU-2',
                'pendingItem' => ' YES ',
                'factory' => 'Level',
                'thickness' => '8',
                'price' => '2000',
                'partnerPrice' => '800',
                'sampleAvailable' => false,
            ],
        ], $products);
    }

    public function test_it_returns_no_products_for_an_unknown_resource(): void
    {
        $products = (new CombinedDataService)->createCombinedData('unknown', []);

        $this->assertSame([], $products);
    }
}
