<?php

namespace App\Services\GoogleSheets;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use RuntimeException;

class GoogleSheetsService
{
    /**
     * Google Sheets service instance
     */
    protected Sheets $service;

    public function __construct()
    {

        // Initialize Google Client
        $client = new Client;

        // Set the application name for the Google Client
        $client->setApplicationName(
            config('services.google_sheets.application_name')
        );

        // Set the scopes for the Google Client
        $client->setScopes([
            Sheets::SPREADSHEETS_READONLY,
        ]);

        // Set the authentication configuration for the Google Client
        $client->setAuthConfig($this->resolveCredentialsPath());

        // Initialize the Google Sheets service with the authenticated client
        $this->service = new Sheets($client);
    }

    /**
     * Get the credentials file path, failing with a clear reason if it is unusable
     */
    protected function resolveCredentialsPath(): string
    {
        // Get the credentials path from the configuration
        $path = (string) config('services.google_sheets.credentials');

        // Check if the path is a directory, which is invalid for credentials
        if (is_dir($path)) {
            throw new RuntimeException(
                "Google Sheets credentials path [{$path}] is a directory. "
                .'GOOGLE_SHEETS_CREDENTIALS is probably missing from .env, or config is cached (clear bootstrap/cache/config.php).'
            );
        }

        // Check if the credentials file exists and is readable
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(
                "Google Sheets credentials file [{$path}] does not exist or is not readable."
            );
        }

        return $path;
    }

    /**
     * Get rows from the Google Sheets spreadsheet
     *
     * @param  string  $tool_type  The type of tool to fetch rows for
     * @return array<int, array<string, mixed>> The rows from the spreadsheet
     */
    public function getRows(string $tool_type = ''): array
    {
        // Sample request tool also needs attributes and locations data
        $extraRanges = [];

        // Determine extra ranges based on the tool type
        if ($tool_type === 'sample-request') {
            $extraRanges = [
                'attributes' => config('services.google_sheets.attributes_range'),
                'locations' => config('services.google_sheets.locations_range'),
            ];
        } elseif ($tool_type === 'xml') {
            $extraRanges = [
                'attributes' => config('services.google_sheets.xml_attributes_range'),
            ];
        }

        // Fetch the main range and any extra ranges in a single API request
        $range = $tool_type === 'xml'
            ? config('services.google_sheets.xml_range')
            : config('services.google_sheets.range');
        $response = $this->service->spreadsheets_values->batchGet(
            config('services.google_sheets.spreadsheet_id'),
            ['ranges' => array_merge([$range], array_values($extraRanges))]
        );

        $valueRanges = $response->getValueRanges();

        // Get the values from the main range
        $rows = $valueRanges[0]->getValues() ?? [];

        // Check if there are any rows to process
        if (! empty($rows)) {
            $keys = $this->formatKeyNames($rows[0]);

            // Create final combined data
            $combined_data = $this->createCombinedData($keys, $rows);
        } else {
            $combined_data = [];
        }

        // Attach extra range data if available
        if ($extraRanges !== []) {
            $extraValueRanges = array_slice($valueRanges, 1);
            $combined_data = $tool_type === 'xml'
                ? $this->attachXmlAttributeData($combined_data, $extraValueRanges)
                : $this->attachRangeData($combined_data, $extraValueRanges);
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
     * Attach attributes and picklist locations to each product, matched on postID
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, ValueRange>  $valueRanges  Attributes range first, then locations range
     * @return array<int, array<string, mixed>>
     */
    protected function attachRangeData(array $products, array $valueRanges): array
    {
        $attributes = $this->formatAttributes($valueRanges[0]->getValues() ?? []);
        $picklists = $this->formatPicklistLocations($valueRanges[1]->getValues() ?? []);

        // Attach the extra range data to each product based on the postID
        foreach ($products as &$product) {
            $postId = $product['postID'] ?? '';

            $product['attributes'] = $attributes[$postId] ?? [];
            $product['picklistLocations'] = $picklists[$postId] ?? [];
        }

        // Unset the reference to the last product to avoid unexpected side effects
        unset($product);

        return $products;
    }

    /**
     * Attach legacy XML attribute data to each product, matched on postID.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, ValueRange>  $valueRanges
     * @return array<int, array<string, mixed>>
     */
    protected function attachXmlAttributeData(array $products, array $valueRanges): array
    {
        $attributes = $this->formatXmlAttributes($valueRanges[0]->getValues() ?? []);

        foreach ($products as &$product) {
            $postId = $product['postID'] ?? '';
            $product['attributes'] = $attributes[$postId] ?? [];
        }

        unset($product);

        return $products;
    }

    /**
     * Preserve the XML generator's source fields, including the raw attributes JSON.
     *
     * @param  array<int, array<int, string>>  $values
     * @return array<string, array<string, string>>
     */
    protected function formatXmlAttributes(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $headers = $this->formatKeyNames(array_shift($values));
        $result = [];

        foreach ($values as $row) {
            $item = array_combine($headers, array_slice(array_pad($row, count($headers), ''), 0, count($headers)));
            $id = (string) ($item['id'] ?? '');
            unset($item['id'], $item['dataEnd']);

            if ($id !== '') {
                $result[$id] = $item;
            }
        }

        return $result;
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

    protected function formatAttributes(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $headers = array_map('strtolower', array_shift($values));
        $result = [];

        foreach ($values as $row) {
            $item = array_combine($headers, array_slice(array_pad($row, count($headers), ''), 0, count($headers)));

            // JSON like {"category": "...", "tag": "..."} becomes one space-separated string
            $item['attributes'] = implode(' ', json_decode($item['attributes'], true) ?? []);

            $id = $item['id'];
            unset($item['id'], $item['data_end']);

            $result[$id] = $item;
        }

        return $result;
    }

    protected function formatPicklistLocations(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $headers = array_map('strtolower', array_shift($values));
        $result = [];

        foreach ($values as $row) {
            $item = array_combine($headers, array_slice(array_pad($row, count($headers), ''), 0, count($headers)));

            $finish = str_replace(' ', '-', $item['finish']);              // Natural R10 -> Natural-R10
            $thickness = str_replace('.', '-', $item['thickness']).'mm';    // 9.5 -> 9-5mm

            $result[$item['post_id']][$finish][$thickness] = $item['location'];
        }

        return $result;
    }
}
