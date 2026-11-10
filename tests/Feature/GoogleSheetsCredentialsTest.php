<?php

namespace Tests\Feature;

use App\Services\GoogleSheets\GoogleSheetsService;
use RuntimeException;
use Tests\TestCase;

class GoogleSheetsCredentialsTest extends TestCase
{
    public function test_it_explains_when_the_credentials_path_is_a_directory(): void
    {
        config(['services.google_sheets.credentials' => storage_path('app/')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is a directory');

        new GoogleSheetsService;
    }

    public function test_it_explains_when_the_credentials_file_is_missing(): void
    {
        config(['services.google_sheets.credentials' => storage_path('app/google/missing.json')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        new GoogleSheetsService;
    }
}
