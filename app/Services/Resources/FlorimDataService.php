<?php

namespace App\Services\Resources;

class FlorimDataService
{
    /**
     * Create the Florim UK JSON data from the shared sheet rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function generate(array $rows): array
    {
        $florimRows = [];

        foreach ($rows as $row) {
            if (strtolower((string) ($row['florimukItem'] ?? '')) !== 'true') {
                continue;
            }

            $row['thickness'] = preg_replace('/[^0-9.]/', '', (string) ($row['thickness'] ?? ''));

            if (str_contains((string) ($row['price'] ?? ''), '£')) {
                $row['partnerPrice'] = $this->stripCurrency($row['partnerPrice'] ?? '');
                $row['price'] = $this->stripCurrency($row['price']);
            }

            $row['sampleAvailable'] = strtolower((string) ($row['sampleAvailable'] ?? '')) === 'true';
            unset($row['dataEnd']);

            if ($this->hasData($row)) {
                $florimRows[] = $row;
            }
        }

        return $florimRows;
    }

    private function stripCurrency(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace(
            ['/[\.,]0{2}$/', '/£|,/'],
            ['', ''],
            $value
        ) ?? $value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasData(array $row): bool
    {
        return $row !== [] && array_filter($row, fn (mixed $value): bool => $value !== null && $value !== '') !== [];
    }
}
