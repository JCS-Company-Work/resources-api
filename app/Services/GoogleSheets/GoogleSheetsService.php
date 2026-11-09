<?php

namespace App\Services\GoogleSheets;

use Google\Client;
use Google\Service\Sheets;

class GoogleSheetsService
{
    protected Sheets $service;

    public function __construct()
    {
        $client = new Client;

        $client->setApplicationName(
            config('services.google_sheets.application_name')
        );

        $client->setScopes([
            Sheets::SPREADSHEETS_READONLY,
        ]);

        $client->setAuthConfig(
            config('services.google_sheets.credentials')
        );

        $this->service = new Sheets($client);
    }

    /**
     * Get rows from the Google Sheets spreadsheet
     */
    public function getRows(): array
    {

        // Get the rows from the spreadsheet
        $response = $this->service->spreadsheets_values->get(
            config('services.google_sheets.spreadsheet_id'),
            config('services.google_sheets.range')
        );

        // Get the values from the response
        $rows = $response->getValues() ?? [];

        // Check if there are any rows to process
        if (! empty($rows)) {
            $keys = $this->formatKeyNames($rows[0]);

            // Create final combined data
            $combined_data = $this->createCombinedData($keys, $rows);
        } else {
            $combined_data = [];
        }

        return $combined_data;
    }

    /**
     * Create combined associative array from keys and rows
     */
    public function createCombinedData(array $keys, array $rows): array
    {

        // Remove the first row as it contains the keys
        array_shift($rows);

        // Array to hold the combined rows
        $combinedRows = [];

        foreach ($rows as $row) {

            // Combine the keys with the current row to create an associative array
            $combinedRow = array_combine($keys, $row);

            // Remove the 'dataEnd' key from the combined row if it exists
            unset($combinedRow['dataEnd']);

            // Skip rows that don't have a product code
            if (! empty($combinedRow['productCode'])) {
                $combinedRows[] = $combinedRow;
            }

        }

        return $combinedRows;

    }

    /**
     * Format the key names from the raw Google Sheets row keys
     */
    public function formatKeyNames(array $raw_keys): array
    {

        // array to hold formatted keys
        $array_keys = [];

        foreach ($raw_keys as $key) {

            // remove all spaces, dashes, slashes and underscores from key names
            $split_keys = preg_split('#-|[| |_|/|)|(|.|:|]#', $key);

            // remove any empty elements from array
            $filtered_keys = array_filter($split_keys);

            // set first array entry to lowercase
            $filtered_keys[0] = strtolower($filtered_keys[0]);

            // push formatted keys to main array
            array_push($array_keys, implode('', $filtered_keys));

        }

        return $array_keys;

    }
}
