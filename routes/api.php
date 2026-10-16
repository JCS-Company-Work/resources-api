<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\ProductFinderController;
use App\Http\Controllers\Api\ResourceUploadController;
use App\Http\Controllers\Api\TMColoursController;
use App\Http\Middleware\VerifyApiToken;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Main PT sheet data routes
Route::get('/products', [DataController::class, 'getAllProducts'])->middleware(VerifyApiToken::class);
Route::get('/products/{id}', [DataController::class, 'getSingleProduct'])->middleware(VerifyApiToken::class);
Route::get('/ranges/{id}', [DataController::class, 'getSingleRange'])->middleware(VerifyApiToken::class);
Route::get('/pending', [DataController::class, 'getPendingProducts'])->middleware(VerifyApiToken::class);
Route::get('/florimuk', [DataController::class, 'getFlorimData'])->middleware(VerifyApiToken::class);

// File upload route
Route::put('/resources/{resource}', [ResourceUploadController::class, 'update'])->middleware(VerifyApiToken::class);

// Product Finder routes
Route::get('/product-finder/filter-panel', [ProductFinderController::class, 'getProductFinderFilterPanel'])->middleware(VerifyApiToken::class);
Route::get('/product-finder/product-data', [ProductFinderController::class, 'getProductFinderData'])->middleware(VerifyApiToken::class);
Route::get('/product-finder/product-ref-data', [ProductFinderController::class, 'getProductFinderRefData'])->middleware(VerifyApiToken::class);

// TM Colours routes
Route::get('/tm-colours/filter-panel', [TMColoursController::class, 'getTmColoursFilterPanel'])->middleware(VerifyApiToken::class);
Route::get('/tm-colours/colour-data', [TMColoursController::class, 'getTmColoursData'])->middleware(VerifyApiToken::class);
Route::get('/tm-colours/colour-ref-data', [TMColoursController::class, 'getTmColoursRefData'])->middleware(VerifyApiToken::class);

// Sample request tool routes
Route::get('/sample-request/{brand}', [DataController::class, 'getSampleRequestData'])->middleware(VerifyApiToken::class);