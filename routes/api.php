<?php

use App\Http\Controllers\Api\CombinedDataController;
use App\Http\Controllers\Api\FlorimDataController;
use App\Http\Controllers\Api\ProductFinderController;
use App\Http\Controllers\Api\ResourceUploadController;
use App\Http\Controllers\Api\SampleRequestController;
use App\Http\Controllers\Api\TMColoursController;
use App\Http\Middleware\VerifyApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Main PT sheet data routes
Route::middleware(VerifyApiToken::using('combined_data_api'))->group(function () {
    Route::get('/products', [CombinedDataController::class, 'getAllProducts']);
    Route::get('/products/{id}', [CombinedDataController::class, 'getSingleProduct']);
    Route::get('/ranges/{id}', [CombinedDataController::class, 'getSingleRange']);
    Route::get('/pending', [CombinedDataController::class, 'getPendingProducts']);
    Route::get('/ptsitetemplates', [CombinedDataController::class, 'getPtSiteTemplates']);
    Route::put('/resources/combined-data/{filename}', [CombinedDataController::class, 'update']);
});

// Florim UK routes
Route::middleware(VerifyApiToken::using('florimuk'))->group(function () {
    Route::get('/florimuk', [FlorimDataController::class, 'getFlorimData']);
});

// File upload resource routes
Route::middleware(VerifyApiToken::using('resource_api'))
    ->group(function () {
        Route::put('/resources/{type}/{filename}', [ResourceUploadController::class, 'update']);
        Route::put('/resources/product-finder/{brand}/{filename}', [ResourceUploadController::class, 'update']);
    });

// Product Finder routes
Route::middleware(VerifyApiToken::using('product_finder_api'))
    ->prefix('product-finder')
    ->group(function () {
        Route::put('/{brand}', [ProductFinderController::class, 'update']);
        Route::get('/all', [ProductFinderController::class, 'getAllProductFinderData']);
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
Route::middleware(VerifyApiToken::using('samples_api'))
    ->prefix('sample-request')
    ->group(function () {
        Route::get('/{brand}', [SampleRequestController::class, 'getSampleRequestData']);
        Route::put('/{brand}', [SampleRequestController::class, 'updateSampleRequestData']);
    });
