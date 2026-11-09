<?php

namespace App\Services\GoogleSheets\Resources;

class CombinedDataService
{
    /**
     * Create combined data based on the filename.
     *
     * @param  string  $filename
     * @param  array  $rows
     * @return array
     */
    public function createCombinedData($filename, $rows)
    {

        if ($filename === 'allbrands') {
            return $this->createAllBrands($rows);
        } elseif ($filename === 'allpending') {
            return $this->createAllPending($rows);
        }

        return [];

    }

    /**
     * Create combined data for all brands.
     *
     * @param  array  $rows
     * @return array
     */
    public function createAllBrands($rows)
    {

        // Array to hold the combined data for all brands
        $assoc_array = [];

        // Loop through each row of combined data
        foreach ($rows as $combined_data) {

            // Set pending status based on the 'pendingItem' column
            $isPending = strtolower(trim($combined_data['pendingItem'] ?? '')) === 'yes';

            // Set factory name if item is Ergon or Provenca (or other specific conditions)
            $combined_data['factory'] = $this->setFactory($combined_data['factory']);

            // Include the row in the combined data only if it's not pending
            if (! $isPending) {

                // Remove 'mm' from thickness values
                $combined_data['thickness'] = preg_replace('/[^0-9.]/', '', $combined_data['thickness']);

                // Strip £ symbol from prices
                $combined_data['partnerPrice'] = strpos($combined_data['price'], '£') !== false
                    ? preg_replace(['@[\.,]0{2}$@', '/£|,/'], ['', ''], $combined_data['partnerPrice'])
                    : $combined_data['partnerPrice'];

                $combined_data['price'] = strpos($combined_data['price'], '£') !== false
                    ? preg_replace(['@[\.,]0{2}$@', '/£|,/'], ['', ''], $combined_data['price'])
                    : $combined_data['price'];

                // Convert sampleAvailable string to boolean
                $combined_data['sampleAvailable'] = strtolower($combined_data['sampleAvailable']) === 'true';

                $assoc_array[] = $combined_data;
            }
        }

        return $assoc_array ?? [];

    }

    /**
     * Create combined data for all pending items.
     *
     * @param  array  $rows
     * @return array
     */
    public function createAllPending($rows)
    {

        $assoc_array = [];

        foreach ($rows as $combined_data) {

            $isPending = strtolower(trim($combined_data['pendingItem'] ?? '')) === 'yes';

            if (! $isPending) {
                continue;
            }

            // Set factory name if item is Ergon or Provenca
            $combined_data['factory'] = $this->setFactory($combined_data['factory']);

            // Remove the 'mm' from thickness values
            $combined_data['thickness'] = preg_replace('/[^0-9.]/', '', $combined_data['thickness']);

            // Strip £ symbol from prices
            $combined_data['partnerPrice'] = strpos($combined_data['price'], '£') !== false ? preg_replace(['/[\.,]0{2}$/', '/£|,/'], ['', ''], $combined_data['partnerPrice']) : $combined_data['partnerPrice'];

            $combined_data['price'] = strpos($combined_data['price'], '£') !== false ? preg_replace(['/[\.,]0{2}$/', '/£|,/'], ['', ''], $combined_data['price']) : $combined_data['price'];

            // Convert sample available string to boolean
            $combined_data['sampleAvailable'] = strtolower($combined_data['sampleAvailable']) === 'true' ? true : false;

            $assoc_array[] = $combined_data;
        }

        return $assoc_array ?? [];
    }

    /**
     * Set the factory name based on specific conditions.
     *
     * @param  string  $factory
     * @return string
     */
    public function setFactory($factory)
    {

        // If item is from new Ergon or Provenza factories, include in Level
        if (strtolower($factory) === 'ergon' || strtolower($factory) === 'provenza') {

            return 'Level';

        } else {

            // set factory name to the value held in the factory column
            return $factory;

        }

    }
}
