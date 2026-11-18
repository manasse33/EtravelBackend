<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DestinationPackageController;
use App\Http\Controllers\OuikenacController;
use App\Http\Controllers\CityTourController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AccommodationController;
use App\Http\Controllers\BlogController;

/*
|--------------------------------------------------------------------------
| ROUTES PUBLIQUES
|--------------------------------------------------------------------------
*/

// Auth
Route::post('/login', [AdminAuthController::class, 'login'])->name('login');

// Reservations
Route::post('/reservations', [ReservationController::class, 'store']);

// Test upload image
Route::post('/testImage', [CityTourController::class, 'storeImageTest']);

// GET publics
Route::get('/countries', [ConfigController::class, 'indexCountries']);
Route::get('/countries/{id}', [ConfigController::class, 'showCountry']);

Route::get('/cities', [ConfigController::class, 'indexCities']);
Route::get('/cities/{id}', [ConfigController::class, 'showCity']);

Route::get('/destinations', [DestinationPackageController::class, 'index']);
Route::get('/destinations/{id}', [DestinationPackageController::class, 'show']);

Route::get('/ouikenac', [OuikenacController::class, 'index']);
Route::get('/ouikenac/{id}', [OuikenacController::class, 'show']);

Route::get('/city-tours', [CityTourController::class, 'index']);
Route::get('/city-tours/{id}', [CityTourController::class, 'show']);

Route::get('/accommodations', [AccommodationController::class, 'index']);
Route::get('/accommodations/{id}', [AccommodationController::class, 'show']);

Route::get('/gallery', [BlogController::class, 'gallery']);
Route::get('/posts', [BlogController::class, 'posts']);
Route::get('/city-activities', [BlogController::class, 'cityActivities']);

/*
|--------------------------------------------------------------------------
| ROUTES PROTEGEES (auth:sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Countries
    Route::post('/countries', [ConfigController::class, 'storeCountry']);
    Route::put('/countries/{id}', [ConfigController::class, 'updateCountry']);
    Route::patch('/countries/{id}', [ConfigController::class, 'updateCountry']);
    Route::delete('/countries/{id}', [ConfigController::class, 'destroyCountry']);

    // Cities
    Route::post('/cities', [ConfigController::class, 'storeCity']);
    Route::put('/cities/{id}', [ConfigController::class, 'updateCity']);
    Route::patch('/cities/{id}', [ConfigController::class, 'updateCity']);
    Route::delete('/cities/{id}', [ConfigController::class, 'destroyCity']);

    // Destinations
    Route::post('/destinations', [DestinationPackageController::class, 'store']);
    Route::put('/destinations/{id}', [DestinationPackageController::class, 'update']);
    Route::delete('/destinations/{id}', [DestinationPackageController::class, 'destroy']);

    // Ouikenac
    Route::post('/ouikenac', [OuikenacController::class, 'store']);
    Route::put('/ouikenac/{id}', [OuikenacController::class, 'update']);
    Route::delete('/ouikenac/{id}', [OuikenacController::class, 'destroy']);

    // City Tours
    Route::post('/city-tours', [CityTourController::class, 'store']);
    Route::put('/city-tours/{id}', [CityTourController::class, 'update']);
    Route::delete('/city-tours/{id}', [CityTourController::class, 'destroy']);

    // Accommodations
    Route::post('/accommodations', [AccommodationController::class, 'store']);
    Route::put('/accommodations/{id}', [AccommodationController::class, 'update']);
    Route::patch('/accommodations/{id}', [AccommodationController::class, 'update']);
    Route::delete('/accommodations/{id}', [AccommodationController::class, 'destroy']);

    // Blog Posts
    Route::post('/posts', [BlogController::class, 'store']);
    Route::put('/posts/{id}', [BlogController::class, 'update']);
    Route::delete('/posts/{id}', [BlogController::class, 'destroy']);

    // Logout
    Route::post('/logout', [AdminAuthController::class, 'logout']);

    // Admin management
    Route::post('/register', [AdminAuthController::class, 'register']);

    // Reservations
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::put('/reservations/{id}/status', [ReservationController::class, 'updateStatus']);
});
