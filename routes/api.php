<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\ProductFinderController;
use App\Http\Controllers\Api\ResourceUploadController;
use App\Http\Controllers\Api\TMColoursController;
use App\Http\Middleware\VerifyResourceApiToken;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Main PT sheet data routes
Route::get('/products', [DataController::class, 'getAllProducts']);
Route::get('/products/{id}', [DataController::class, 'getSingleProduct']);
Route::get('/ranges/{id}', [DataController::class, 'getSingleRange']);
Route::get('/pending', [DataController::class, 'getPendingProducts']);
Route::get('/florimuk', [DataController::class, 'getFlorimData']);
Route::put('/resources/{resource}', [ResourceUploadController::class, 'update'])->middleware(VerifyResourceApiToken::class);

// Product Finder routes
Route::get('/product-finder/filter-panel', [ProductFinderController::class, 'getProductFinderFilterPanel']);
Route::get('/product-finder/product-data', [ProductFinderController::class, 'getProductFinderData']);
Route::get('/product-finder/product-ref-data', [ProductFinderController::class, 'getProductFinderRefData']);

// TM Colours routes
Route::get('/tm-colours/filter-panel', [TMColoursController::class, 'getTmColoursFilterPanel']);
Route::get('/tm-colours/colour-data', [TMColoursController::class, 'getTmColoursData']);
Route::get('/tm-colours/colour-ref-data', [TMColoursController::class, 'getTmColoursRefData']);