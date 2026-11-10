# Resources API

A Laravel API that serves JSON resources for the product catalogue, sample request tool, product finder, and TM Colours. Resources are read from private storage and cached; the combined-data update endpoint can regenerate its files from Google Sheets.

## Requirements

- PHP 8.3+
- Composer
- A Google service-account credentials JSON file for combined-data generation

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Set the environment variables listed below in `.env`. For Google Sheets access, place the credentials file under `storage/app/` and set `GOOGLE_SHEETS_CREDENTIALS` to its relative path. The service account needs read access to the configured spreadsheet.

Start the API:

```bash
php artisan serve
```

API routes are served under `/api`.

## Configuration

Configure a distinct token for each API client:

| Environment variable | Used by |
| --- | --- |
| `COMBINED_DATA_API_TOKEN` | Combined-data reads and generation |
| `FLORIMUK_API_TOKEN` | Florim UK data |
| `SAMPLES_API_TOKEN` | Sample request data |
| `PRODUCT_FINDER_API_TOKEN` | Product Finder data |
| `TM_COLOURS_API_TOKEN` | TM Colours data |
| `RESOURCE_API_TOKEN` | Legacy JSON resource upload endpoints |

Send tokens in the `X-API-Token` request header.

Combined-data generation also requires:

| Environment variable | Purpose |
| --- | --- |
| `GOOGLE_SHEETS_APPLICATION_NAME` | Google API client application name |
| `GOOGLE_SHEETS_SPREADSHEET_ID` | Source spreadsheet ID |
| `GOOGLE_SHEETS_RANGE` | Sheet range to read |
| `GOOGLE_SHEETS_CREDENTIALS` | Credentials JSON path relative to `storage/app/` |

The optional `HUB_CACHE_PURGE_URL` and `HUB_CACHE_PURGE_TOKEN` configure the Hub cache purge integration.

## API endpoints

Data and resource routes require the corresponding token shown below. `GET /api/user` is the exception; it uses Sanctum authentication.

### Combined data

Uses `COMBINED_DATA_API_TOKEN`.

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/products` | All products |
| `GET` | `/api/products/{id}` | Product by post ID |
| `GET` | `/api/ranges/{id}` | Products in a range |
| `GET` | `/api/pending` | Pending products |
| `GET` | `/api/ptsitetemplates` | Product data with hidden fields excluded |
| `PUT` | `/api/resources/combined-data/{filename}` | Generate and save `allbrands` or `allpending` from Google Sheets |

The generation endpoint takes no JSON body. For example:

```bash
curl -X PUT http://localhost:8000/api/resources/combined-data/allbrands \
  -H 'X-API-Token: YOUR_COMBINED_DATA_API_TOKEN'
```

Generated files are stored under `storage/app/private/combined-data/`.

### Other data endpoints

| Method | Path | Token |
| --- | --- | --- |
| `GET` | `/api/florimuk` | `FLORIMUK_API_TOKEN` |
| `GET` | `/api/sample-request/{brand}` | `SAMPLES_API_TOKEN` |
| `GET` | `/api/product-finder/all` | `PRODUCT_FINDER_API_TOKEN` |
| `GET` | `/api/product-finder/filter-panel` | `PRODUCT_FINDER_API_TOKEN` |
| `GET` | `/api/product-finder/product-data` | `PRODUCT_FINDER_API_TOKEN` |
| `GET` | `/api/product-finder/product-ref-data` | `PRODUCT_FINDER_API_TOKEN` |
| `GET` | `/api/tm-colours/filter-panel` | `TM_COLOURS_API_TOKEN` |
| `GET` | `/api/tm-colours/colour-data` | `TM_COLOURS_API_TOKEN` |
| `GET` | `/api/tm-colours/colour-ref-data` | `TM_COLOURS_API_TOKEN` |

### Legacy resource uploads

The JSON upload endpoints remain available during migration to generated resources. They accept JSON request bodies and use `RESOURCE_API_TOKEN`:

- `PUT /api/resources/{type}/{filename}`
- `PUT /api/resources/product-finder/{brand}/{filename}`

## Resource files and caching

JSON resources are stored in private local storage, under `storage/app/private/`. Reads are cached for 30 days. Resource generation clears the matching resource cache after writing the new file.

## Tests

Run the test suite with:

```bash
php artisan test --compact
```
