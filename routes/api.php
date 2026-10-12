<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\ResourceUploadController;
use App\Http\Middleware\VerifyResourceApiToken;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Main PT sheet data routes
Route::get('/products/{id}', [DataController::class, 'getSingleProduct']);
Route::get('/range/{id}', [DataController::class, 'getRange']);
Route::put('/resources/{resource}', [ResourceUploadController::class, 'update'])->middleware(VerifyResourceApiToken::class);