<?php

use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\ProductFinderController;
use App\Http\Controllers\Api\ResourceUploadController;
use App\Http\Controllers\Api\TMColoursController;
use App\Http\Middleware\VerifyApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Main PT sheet data routes
Route::middleware(VerifyApiToken::using('combined_data_api'))->group(function () {
    Route::get('/products', [DataController::class, 'getAllProducts']);
    Route::get('/products/{id}', [DataController::class, 'getSingleProduct']);
    Route::get('/ranges/{id}', [DataController::class, 'getSingleRange']);
    Route::get('/pending', [DataController::class, 'getPendingProducts']);
});

// Florim UK routes
Route::middleware(VerifyApiToken::using('florimuk'))->group(function () {
    Route::get('/florimuk', [DataController::class, 'getFlorimData']);
});

// File upload route
Route::put('/resources/{type}/{resource}', [ResourceUploadController::class, 'update'])
    ->middleware(VerifyApiToken::using('resource_api'));

// Product Finder routes
Route::middleware(VerifyApiToken::using('product_finder_api'))
    ->prefix('product-finder')
    ->group(function () {
        Route::get('/filter-panel', [ProductFinderController::class, 'getProductFinderFilterPanel']);
        Route::get('/product-data', [ProductFinderController::class, 'getProductFinderData']);
        Route::get('/product-ref-data', [ProductFinderController::class, 'getProductFinderRefData']);
    });

// TM Colours routes
Route::middleware(VerifyApiToken::using('tm_colours_api'))
    ->prefix('tm-colours')
    ->group(function () {
        Route::get('/filter-panel', [TMColoursController::class, 'getTmColoursFilterPanel']);
        Route::get('/colour-data', [TMColoursController::class, 'getTmColoursData']);
        Route::get('/colour-ref-data', [TMColoursController::class, 'getTmColoursRefData']);
    });

// Sample request tool routes
Route::get('/sample-request/{brand}', [DataController::class, 'getSampleRequestData'])
    ->middleware(VerifyApiToken::using('samples_api'));
